<?php

namespace App\Services;

use App\Models\LinkPreview;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Récupère un aperçu Open Graph pour la première URL d'un message (façon
 * WhatsApp) : titre, description, site et image. Les résultats sont mis en cache
 * par empreinte d'URL et l'image est téléchargée et hébergée localement.
 *
 * Toute requête sortante est validée contre le SSRF (schéma http/https, hôte
 * public, redirections re-vérifiées) avant d'être émise.
 */
class LinkPreviewService
{
    /** Durée de validité d'une entrée de cache, en jours. */
    public const TTL_DAYS = 7;

    private const MAX_HTML_BYTES = 2_000_000;

    private const MAX_IMAGE_BYTES = 3_000_000;

    private const MAX_REDIRECTS = 3;

    private const TIMEOUT = 5;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * Première URL absolue trouvée dans un texte, sans ponctuation finale
     * collée (point, virgule, parenthèse fermante non ouvrante…). Une URL sans
     * schéma mais préfixée « www. » est normalisée en http://.
     */
    public function extractFirstUrl(string $text): ?string
    {
        if (! preg_match('~(https?://|www\.)[^\s<>"\']+~i', $text, $matches)) {
            return null;
        }

        return $this->normalizeUrl($matches[0]);
    }

    /**
     * Normalise une URL : retire la ponctuation finale collée et ajoute le
     * schéma http:// à une URL « www. » sans schéma. Renvoie null si l'entrée
     * ne ressemble pas à une URL exploitable.
     */
    public function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        // Retire la ponctuation terminale courante (hors parenthèses appariées).
        $url = rtrim($url, '.,;:!?');

        while (str_ends_with($url, ')') && substr_count($url, '(') < substr_count($url, ')')) {
            $url = substr($url, 0, -1);
        }

        if ($url === '') {
            return null;
        }

        if (preg_match('~^www\.~i', $url)) {
            $url = 'http://'.$url;
        }

        return preg_match('~^https?://~i', $url) ? $url : null;
    }

    /**
     * Résout l'aperçu d'une URL : cache frais s'il existe, sinon scrape Open
     * Graph puis met en cache. Renvoie null si l'URL est invalide, insécure ou
     * injoignable.
     *
     * @return array{url: string, title: ?string, description: ?string, site: ?string, image_path: ?string}|null
     */
    public function resolve(string $url): ?array
    {
        $url = $this->normalizeUrl($url);

        if ($url === null || ! $this->isSafeUrl($url)) {
            return null;
        }

        $hash = hash('sha256', $url);
        $cached = LinkPreview::where('url_hash', $hash)->first();

        if ($cached && $cached->fetched_at && $cached->fetched_at->gt(now()->subDays(self::TTL_DAYS))) {
            return $this->present($cached);
        }

        $data = $this->scrape($url);

        if ($data === null) {
            return null;
        }

        $preview = $cached ?? new LinkPreview(['url_hash' => $hash]);
        $preview->fill($data + ['url' => $url, 'fetched_at' => now()])->save();

        return $this->present($preview);
    }

    /**
     * Vrai si l'URL est sûre à récupérer : schéma http/https, hôte résolu et
     * uniquement vers des adresses IP publiques (anti-SSRF).
     */
    public function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        $ips = $this->resolveHost($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{url: string, title: ?string, description: ?string, site: ?string, image_path: ?string}
     */
    private function present(LinkPreview $preview): array
    {
        return [
            'url' => $preview->url,
            'title' => $preview->title,
            'description' => $preview->description,
            'site' => $preview->site,
            'image_path' => $preview->image_path,
        ];
    }

    /**
     * @return array{title: ?string, description: ?string, site: ?string, image_path: ?string}|null
     */
    private function scrape(string $url): ?array
    {
        $response = $this->fetch($url, self::MAX_HTML_BYTES, 'text/html,application/xhtml+xml');

        if ($response === null || $response['status'] < 200 || $response['status'] >= 300) {
            return null;
        }

        $html = $response['body'];
        $base = $response['url'];

        $title = $this->metaContent($html, 'og:title') ?? $this->pageTitle($html);
        $description = $this->metaContent($html, 'og:description') ?? $this->metaContent($html, 'description');
        $site = $this->metaContent($html, 'og:site_name') ?? (parse_url($base, PHP_URL_HOST) ?: null);

        $title = $this->clean($title);
        $description = $this->clean($description);
        $site = $this->clean($site);

        $imagePath = null;
        $imageUrl = $this->metaContent($html, 'og:image') ?? $this->metaContent($html, 'twitter:image');

        if (filled($imageUrl)) {
            $imagePath = $this->downloadImage($this->absoluteUrl($base, $imageUrl));
        }

        // Aperçu inintéressant : ni titre ni texte.
        if (blank($title) && blank($description)) {
            return null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'site' => $site,
            'image_path' => $imagePath,
        ];
    }

    /**
     * Requête GET avec suivi manuel des redirections, chaque cible re-vérifiée
     * contre le SSRF. Le corps est lu en flux et plafonné : les balises meta
     * Open Graph vivent dans l'en-tête du document, inutile de télécharger des
     * pages entières de plusieurs mégaoctets. Renvoie null en cas d'échec ou de
     * cible interdite.
     *
     * @return array{status: int, body: string, url: string, content_type: string}|null
     */
    private function fetch(string $url, int $maxBytes, string $accept): ?array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->isSafeUrl($current)) {
                return null;
            }

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'decode_content' => true,
                    'timeout' => self::TIMEOUT,
                    'connect_timeout' => self::TIMEOUT,
                ])->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => $accept,
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ])->get($current);
            } catch (Throwable) {
                return null;
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');
                if (blank($location)) {
                    return null;
                }
                $current = $this->absoluteUrl($current, $location);

                continue;
            }

            $body = $this->readCapped($response, $maxBytes);

            return [
                'status' => $status,
                'body' => $body,
                'url' => $current,
                'content_type' => strtolower((string) $response->header('Content-Type')),
            ];
        }

        return null;
    }

    /**
     * Lit le corps de la réponse en flux en s'arrêtant au plafond demandé.
     */
    private function readCapped(Response $response, int $maxBytes): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(16384);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
            if (strlen($body) >= $maxBytes) {
                break;
            }
        }

        return $body;
    }

    /**
     * Télécharge l'image distante et l'héberge sur le disque public. Renvoie le
     * chemin stocké, ou null si l'image est invalide, trop lourde ou absente.
     */
    private function downloadImage(?string $imageUrl): ?string
    {
        if (blank($imageUrl) || ! $this->isSafeUrl($imageUrl)) {
            return null;
        }

        $response = $this->fetch($imageUrl, self::MAX_IMAGE_BYTES, 'image/*');

        if ($response === null || $response['status'] < 200 || $response['status'] >= 300) {
            return null;
        }

        if (! str_starts_with($response['content_type'], 'image/')) {
            return null;
        }

        $binary = $response['body'];

        // Image tronquée au plafond de lecture : on refuse plutôt que de
        // stocker un fichier corrompu.
        if ($binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES || ! @getimagesizefromstring($binary)) {
            return null;
        }

        $extension = $this->imageExtension($response['content_type'], $imageUrl);

        if ($extension === null) {
            return null;
        }

        $path = 'discussion-link-previews/'.substr(hash('sha256', $imageUrl), 0, 40).'.'.$extension;

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $binary);
        }

        return $path;
    }

    private function imageExtension(string $contentType, string $imageUrl): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ];

        $mime = trim(explode(';', $contentType)[0]);

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        $path = strtolower((string) parse_url($imageUrl, PHP_URL_PATH));

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'] as $ext) {
            if (str_ends_with($path, '.'.$ext)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        return null;
    }

    /**
     * Contenu d'une balise meta (propriété ou nom), dans n'importe quel ordre
     * d'attributs.
     */
    private function metaContent(string $html, string $key): ?string
    {
        $pattern = '~<meta[^>]+(?:property|name)=["\']'.preg_quote($key, '~').'["\'][^>]*>~i';

        if (! preg_match($pattern, $html, $tag)) {
            return null;
        }

        if (! preg_match('~content=["\'](.*?)["\']~is', $tag[0], $content)) {
            return null;
        }

        return html_entity_decode(trim($content[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function pageTitle(string $html): ?string
    {
        if (! preg_match('~<title[^>]*>(.*?)</title>~is', $html, $matches)) {
            return null;
        }

        return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function clean(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value);

        return trim(mb_substr((string) $value, 0, 500));
    }

    /**
     * Résout une URL (éventuellement relative) contre l'URL de base.
     */
    private function absoluteUrl(string $base, string $relative): string
    {
        $relative = trim($relative);

        if (preg_match('~^https?://~i', $relative)) {
            return $relative;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $relative;
        }

        $root = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($relative, '//')) {
            return $parts['scheme'].':'.$relative;
        }

        if (str_starts_with($relative, '/')) {
            return $root.$relative;
        }

        $dir = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';

        return $root.$dir.'/'.$relative;
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach ((array) $records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            $ip = gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Filets de sécurité explicites (loopback, link-local IPv4, ULA/link-local IPv6).
        $blocked = ['127.', '0.', '169.254.', '::1', 'fc', 'fd', 'fe80'];

        foreach ($blocked as $prefix) {
            if (str_starts_with(strtolower($ip), $prefix)) {
                return false;
            }
        }

        return true;
    }
}
