<?php

namespace App\Http\Controllers;

use App\Models\GifFavorite;
use App\Models\Message;
use App\Models\MessageDeletion;
use App\Services\ActivityService;
use App\Services\PushService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class DiscussionController extends Controller
{
    /**
     * Nombre de messages chargés par lot : au démarrage (fin du fil), puis à
     * chaque clic sur « charger plus » en haut du fil. L'historique n'est plus
     * envoyé en un seul bloc.
     */
    public const PAGINATION = 50;

    /**
     * URL racine-relative d'une photo de discussion (ex. `/storage/discussion-photos/x.png`).
     * Contrairement à Storage::url(), on évite l'URL absolue construite à partir
     * d'APP_URL : le destinataire charge ainsi toujours l'image depuis son propre
     * domaine, même s'il accède via une IP LAN ou un autre nom d'hôte.
     */
    protected function photoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: null;
    }

    protected function audioUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: null;
    }

    protected function videoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: null;
    }

    /**
     * Requête de base des messages visibles par un utilisateur : ceux du couple,
     * moins ceux qu'il a supprimés, avec les relations utiles aux bulles.
     *
     * @return Builder<Message>
     */
    protected function messagesQuery($couple, int $userId)
    {
        return Message::where('couple_id', $couple->id)
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $userId))
            ->with(['sender:id,name,avatar_url', 'replyTo:id,body,sender_id,gif_url,photo_path,video_path,video_poster_path,audio_path,audio_duration']);
    }

    /**
     * Un lot de messages du plus ancien au plus récent, pris en dessous de
     * `$avantId` (pagination « charger plus »), et s'il en reste sous le plus
     * ancien de ce lot.
     *
     * @return array{0: Collection, 1: bool}
     */
    protected function lotAnciens($couple, int $userId, int $avantId): array
    {
        $query = $this->messagesQuery($couple, $userId);

        $lot = (clone $query)
            ->where('id', '<', $avantId)
            ->orderByDesc('id')
            ->limit(self::PAGINATION)
            ->get()
            ->reverse()
            ->values();

        $premierId = $lot->first()?->id;
        $reste = $premierId !== null
            && (clone $query)->where('id', '<', $premierId)->exists();

        return [$lot, $reste];
    }

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $partner = $couple->partnerOf(Auth::user());

        $this->marquerLus($couple, Auth::user());

        // Fin du fil uniquement (sérialisée, sans HTML) : le JS construit ces
        // bulles avec buildBubble AVANT la première peinture — l'affichage reste
        // strictement le rendu JS habituel, l'ouverture arrive directe en bas.
        // Le reste de l'historique part de bouton « charger plus » en haut du fil.
        $messages = $this->messagesQuery($couple, Auth::id())
            ->orderByDesc('id')
            ->limit(self::PAGINATION)
            ->get()
            ->reverse()
            ->values();

        $premierId = $messages->first()?->id;
        $hasAnciens = $premierId !== null
            && $this->messagesQuery($couple, Auth::id())->where('id', '<', $premierId)->exists();

        return view('discussion.index', [
            'couple' => $couple,
            'partner' => $partner,
            'messages' => $this->mapMessages($couple, Auth::id(), $messages),
            'pagination' => self::PAGINATION,
            'hasAnciens' => $hasAnciens,
        ]);
    }

    /**
     * Transforme une collection de messages en le tableau sérialisable (même
     * forme que la réponse du fetch) utilisé par le JS pour le rendu.
     */
    protected function mapMessages($couple, int $userId, $messages): array
    {
        return $messages->map(function (Message $m) use ($userId) {
            $deletedForAll = $m->isDeletedForAll();

            return [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'sender_name' => $m->sender?->name ?? 'Ancien·ne partenaire',
                'body' => $deletedForAll ? null : $m->body,
                'gif_url' => $deletedForAll ? null : $m->gif_url,
                'gif_alt' => $deletedForAll ? null : $m->gif_alt,
                'photo_url' => $deletedForAll ? null : $this->photoUrl($m->photo_path),
                'photo_w' => $deletedForAll ? null : $m->photo_w,
                'photo_h' => $deletedForAll ? null : $m->photo_h,
                'video_url' => $deletedForAll ? null : $this->videoUrl($m->video_path),
                'video_w' => $deletedForAll ? null : $m->video_w,
                'video_h' => $deletedForAll ? null : $m->video_h,
                'video_poster_url' => $deletedForAll ? null : $this->photoUrl($m->video_poster_path),
                'audio_url' => $deletedForAll ? null : $this->audioUrl($m->audio_path),
                'audio_duration' => $deletedForAll ? null : $m->audio_duration,
                'audio_bars' => $deletedForAll ? null : $m->audio_bars,
                'is_gif' => $deletedForAll ? false : $m->isGif(),
                'is_photo' => $deletedForAll ? false : $m->isPhoto(),
                'is_video' => $deletedForAll ? false : $m->isVideo(),
                'is_audio' => $deletedForAll ? false : $m->isAudio(),
                'sender_photo_url' => $m->sender?->avatar_url ? '/storage/'.$m->sender->avatar_url : null,
                'lu' => $m->isRead(),
                'edited' => $deletedForAll ? false : $m->isEdited(),
                'deleted_for_all' => $deletedForAll,
                'deleted_by_me' => $deletedForAll && $m->deleted_by === $userId,
                'created_at' => $m->created_at->utc()->toIso8601String(),
                'reply_to' => $m->replyTo && ! $deletedForAll ? [
                    'id' => $m->replyTo->id,
                    'sender_id' => $m->replyTo->sender_id,
                    'sender_name' => $m->replyTo->sender?->name ?? 'Ancien·ne partenaire',
                    'body' => $m->replyTo->body,
                    'is_gif' => $m->replyTo->isGif(),
                    'gif_url' => $m->replyTo->gif_url,
                    'is_photo' => $m->replyTo->isPhoto(),
                    'photo_url' => $this->photoUrl($m->replyTo->photo_path),
                    'is_video' => $m->replyTo->isVideo(),
                    'video_url' => $this->videoUrl($m->replyTo->video_path),
                    'video_poster_url' => $this->photoUrl($m->replyTo->video_poster_path),
                    'is_audio' => $m->replyTo->isAudio(),
                    'audio_duration' => $m->replyTo->audio_duration,
                ] : null,
            ];
        })->values()->all();
    }

    public function fetch(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        // Être dans la discussion = être en ligne.
        ActivityService::touch($request->user());

        $this->marquerLus($couple, $request->user());

        $apresId = (int) $request->query('after', 0);
        $avantId = (int) $request->query('before', 0);
        $userId = $request->user()->id;

        $query = $this->messagesQuery($couple, $userId);

        if ($avantId > 0) {
            // Pagination « charger plus » : le lot situé juste au-dessus de ce que
            // le client a déjà affiché, du plus ancien au plus récent.
            [$messages, $hasAnciens] = $this->lotAnciens($couple, $userId, $avantId);
        } elseif ($apresId > 0) {
            // Poll incrémental : seuls les nouveaux messages depuis le dernier id.
            $messages = (clone $query)
                ->where('id', '>', $apresId)
                ->orderBy('id')
                ->get();
            $hasAnciens = null;
        } else {
            // Chargement initial : la fin du fil uniquement, du plus ancien au
            // plus récent (l'historique se poursuit par le bouton en haut).
            $messages = (clone $query)
                ->orderByDesc('id')
                ->limit(self::PAGINATION)
                ->get()
                ->reverse()
                ->values();
            $premierId = $messages->first()?->id;
            $hasAnciens = $premierId !== null
                && (clone $query)->where('id', '<', $premierId)->exists();
        }

        $messages = $this->mapMessages($couple, $userId, $messages);

        $nonLus = Message::where('couple_id', $couple->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        // Le poll n'étendant plus l'historique, deux deltas complètent `messages` :
        // les ids de MES messages passés « lu » (✓✓), et les messages modifiés
        // (édition du texte côté partenaire).
        //
        // Le curseur vient du SERVEUR, jamais de l'horloge du navigateur. Un
        // curseur horodaté côté client crée une fenêtre aveugle à chaque poll : le
        // navigateur estampille son curseur APRÈS avoir reçu la réponse, donc plus
        // tard que l'instant où le serveur a interrogé la base. Les lectures
        // survenues entre les deux (réponse en vol + écart entre les horloges) se
        // retrouvent derrière le curseur, sans qu'aucun poll ait jamais pu les
        // voir ; le curseur ne redescendant jamais, elles sont perdues
        // définitivement. D'où des ✓✓ apparaissant ALÉATOIREMENT, selon la phase
        // du cycle de poll où tombe la lecture.
        //
        // L'ordre des opérations ci-dessous est ce qui ferme la fenêtre :
        //
        //   1. on LIT le curseur courant, c'est-à-dire l'état réel de la base à
        //      cet instant ;
        //   2. on calcule le delta entre le curseur REÇU et celui-là, avec une
        //      borne haute ;
        //   3. on renvoie ce même curseur pour le prochain appel.
        //
        // Toute lecture survenue après l'étape 1 porte un read_at postérieur au
        // curseur : elle sort du delta courant mais reste au-dessus du curseur
        // renvoyé, donc elle est servie au poll suivant. Aucune perte possible.
        //
        // Les 200 plus récentes sont renvoyées en priorité (ordre décroissant) :
        // en cas de rafale, ce sont elles qui sont affichées à l'écran, et
        // l'élagage des plus anciennes ne crée pas de trou puisque le curseur
        // reste le maximum réel.
        $curseur = $this->curseurSync($couple, $userId);
        $lusDepuis = $this->parseCurseur($request->query('lusSince'));
        $modifiesDepuis = $this->parseCurseur($request->query('modifiesSince'));
        $lus = [];
        $modifies = [];

        if ($curseur['lus']) {
            $lus = Message::where('couple_id', $couple->id)
                ->where('sender_id', $userId)
                ->whereNotNull('read_at')
                ->when($lusDepuis, fn ($q) => $q->where('read_at', '>', $lusDepuis))
                ->where('read_at', '<=', $this->parseCurseur($curseur['lus']))
                ->orderByDesc('read_at')
                ->orderByDesc('id')
                ->limit(200)
                ->pluck('id')
                ->all();
        }

        if ($curseur['modifies']) {
            $modifies = $this->mapMessages($couple, $userId, (clone $query)
                ->whereNotNull('edited_at')
                ->when($modifiesDepuis, fn ($q) => $q->where('edited_at', '>', $modifiesDepuis))
                ->where('edited_at', '<=', $this->parseCurseur($curseur['modifies']))
                ->orderByDesc('edited_at')
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->sortBy('id')
                ->values());
        }

        $partenaire = $couple->partnerOf($request->user())?->fresh();

        return response()->json([
            'messages' => $messages,
            'hasAnciens' => $hasAnciens,
            'lus' => $lus,
            'modifies' => $modifies,
            // Curseur de synchronisation des ✓✓ et des éditions : c'est celui
            // CAPTURÉ AVANT le calcul des deltas ci-dessus, donc l'état réel de la
            // base au moment où le delta a été figé. Le renvoyer tel quel garantit
            // qu'aucun événement ne saute, et qu'aucun ne soit rejoué.
            'curseur' => $curseur,
            'nonLus' => $nonLus,
            'partenaire' => [
                'enLigne' => $partenaire?->last_active_at !== null && $partenaire->last_active_at->diffInMinutes() < 1,
                'present' => $partenaire?->last_active_at !== null,
                'heure' => $partenaire?->last_active_at?->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE),
                'typing' => $partenaire?->typing_at !== null && $partenaire->typing_at->diffInSeconds() < 3,
                'recording' => $partenaire?->recording_at !== null && $partenaire->recording_at->diffInSeconds() < 3,
            ],
        ]);
    }

    public function typing(Request $request): JsonResponse
    {
        // Signale que l'utilisateur est en train d'écrire ; l'indicateur expire seul
        // après quelques secondes (le dernier typage est renvoyé via fetch).
        $request->user()->forceFill(['typing_at' => now()])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true]);
    }

    public function recording(Request $request): JsonResponse
    {
        // Signale que l'utilisateur est en train d'enregistrer un message vocal ;
        // l'indicateur expire seul après quelques secondes (rafraîchi pendant
        // l'enregistrement, renvoyé via fetch).
        $request->user()->forceFill(['recording_at' => now()])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true]);
    }

    public function gifs(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $apiKey = config('services.giphy.key');
        if (! $apiKey) {
            return response()->json(['error' => 'La clé GIPHY n\'est pas configurée.'], 503);
        }

        $params = [
            'api_key' => $apiKey,
            'limit' => 24,
            'rating' => 'g',
            'lang' => 'fr',
        ];

        // Sans mot-clé : stickers/emojis tendance ; sinon recherche.
        $endpoint = $query === ''
            ? 'https://api.giphy.com/v1/stickers/trending'
            : 'https://api.giphy.com/v1/stickers/search';

        if ($query !== '') {
            $params['q'] = $query;
        }

        $response = Http::get($endpoint, $params);

        if ($response->failed() || ! isset($response->json()['data'])) {
            return response()->json(['error' => 'Impossible de contacter GIPHY.'], 502);
        }

        $gifs = collect($response->json()['data'])->map(fn ($g) => [
            'id' => $g['id'] ?? null,
            'title' => $g['title'] ?? '',
            'alt' => $g['alt_text'] ?? ($g['title'] ?? ''),
            'url' => $g['images']['original']['url'] ?? null,
            'preview' => $g['images']['downsized']['url'] ?? ($g['images']['fixed_width']['url'] ?? null),
        ])->filter(fn ($g) => $g['url'] !== null)->values();

        return response()->json(['gifs' => $gifs]);
    }

    /**
     * Pack de stickers hébergés localement (aucune dépendance externe).
     * L'onglet « Stickers » du panneau s'appuie sur ces URLs, qui pointent
     * vers le domaine de l'app : le destinataire les charge donc toujours,
     * même hors-ligne (mise en cache par le Service Worker).
     */
    public function stickers(): JsonResponse
    {
        $manifestPath = public_path('stickers/manifest.json');

        if (! file_exists($manifestPath)) {
            return response()->json(['stickers' => []]);
        }

        $stickers = collect(json_decode((string) file_get_contents($manifestPath), true))
            ->map(fn (array $s): array => [
                'url' => asset('stickers/'.$s['file']),
                'alt' => $s['alt'] ?? '',
                'local' => true,
            ])
            ->values();

        return response()->json(['stickers' => $stickers]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $favorites = $couple->gifFavorites()
            ->orderByDesc('id')
            ->get()
            ->map(fn (GifFavorite $f) => [
                'id' => $f->id,
                'url' => $f->gif_url,
                'alt' => $f->gif_alt ?? '',
            ]);

        return response()->json(['favorites' => $favorites]);
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $data = $request->validate([
            'gif_url' => ['required', 'url', 'max:1000'],
            'gif_alt' => ['nullable', 'string', 'max:255'],
        ]);

        // On utilise la version URL comme clé : l'URL "original" d'un GIF est stable
        // (la même vignette revient souvent), c'est une clé fiable pour un favori.
        $key = $data['gif_url'];

        $existing = $couple->gifFavorites()->where('gif_url', $key)->first();

        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            $couple->gifFavorites()->create([
                'gif_url' => $key,
                'gif_alt' => $data['gif_alt'] ?? null,
            ]);
            $isFavorite = true;
        }

        $favorites = $couple->gifFavorites()
            ->orderByDesc('id')
            ->get()
            ->map(fn (GifFavorite $f) => [
                'id' => $f->id,
                'url' => $f->gif_url,
                'alt' => $f->gif_alt ?? '',
            ]);

        return response()->json(['favorite' => $isFavorite, 'favorites' => $favorites]);
    }

    public function send(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'gif_url' => ['nullable', 'url', 'max:1000'],
            'gif_alt' => ['nullable', 'string', 'max:255'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'photo_w' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'photo_h' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'video_path' => ['nullable', 'string', 'max:255'],
            'video_w' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'video_h' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'video_poster_path' => ['nullable', 'string', 'max:255'],
            'audio_path' => ['nullable', 'string', 'max:255'],
            'audio_duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'audio_bars' => ['nullable', 'string', 'max:2048'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        // Un message doit contenir du texte, un GIF, une photo, une vidéo ou un vocal.
        if (blank($data['body'] ?? null) && blank($data['gif_url'] ?? null) && blank($data['photo_path'] ?? null) && blank($data['video_path'] ?? null) && blank($data['audio_path'] ?? null)) {
            return response()->json(['error' => 'Le message est vide.'], 422);
        }

        // Vérifier que le fichier existe sur le disque public.
        if (! blank($data['photo_path'] ?? null) && ! Storage::disk('public')->exists($data['photo_path'])) {
            return response()->json(['error' => 'Photo introuvable.'], 422);
        }
        if (! blank($data['video_path'] ?? null) && ! Storage::disk('public')->exists($data['video_path'])) {
            return response()->json(['error' => 'Vidéo introuvable.'], 422);
        }
        if (! blank($data['video_poster_path'] ?? null) && ! Storage::disk('public')->exists($data['video_poster_path'])) {
            return response()->json(['error' => 'Miniature de vidéo introuvable.'], 422);
        }
        if (! blank($data['audio_path'] ?? null) && ! Storage::disk('public')->exists($data['audio_path'])) {
            return response()->json(['error' => 'Vocal introuvable.'], 422);
        }

        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId !== null) {
            $exists = Message::where('id', $replyToId)->where('couple_id', $couple->id)->exists();
            if (! $exists) {
                return response()->json(['error' => 'Ce message n\'existe plus.'], 422);
            }
        }

        $message = Message::create([
            'couple_id' => $couple->id,
            'sender_id' => $request->user()->id,
            'body' => $data['body'] ?? '',
            'gif_url' => $data['gif_url'] ?? null,
            'gif_alt' => $data['gif_alt'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'photo_w' => $data['photo_w'] ?? null,
            'photo_h' => $data['photo_h'] ?? null,
            'video_path' => $data['video_path'] ?? null,
            'video_w' => $data['video_w'] ?? null,
            'video_h' => $data['video_h'] ?? null,
            'video_poster_path' => $data['video_poster_path'] ?? null,
            'audio_path' => $data['audio_path'] ?? null,
            'audio_duration' => $data['audio_duration'] ?? null,
            'audio_bars' => $data['audio_bars'] ?? null,
            'reply_to_id' => $replyToId,
        ]);

        ActivityService::touch($request->user());

        $partner = $couple->partnerOf($request->user());
        if ($partner) {
            $notifBody = ! empty($data['audio_path'] ?? null)
                ? '🎤 Message vocal'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                : (! empty($data['photo_path'] ?? null)
                    ? '📷 Envoie une photo'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                    : (! empty($data['video_path'] ?? null)
                        ? '📹 Envoie une vidéo'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                        : (! empty($data['gif_url'] ?? null)
                            ? '📷 Envoie un GIF'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                            : ($data['body'] ?? 'Nouveau message'))));
            $nonLus = Message::where('couple_id', $couple->id)
                ->where('sender_id', '!=', $partner->id)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->count();
            app(PushService::class)->sendToUser($partner, [
                'title' => '💬 '.$request->user()->name,
                'body' => mb_strimwidth($notifBody, 0, 80, '…'),
                'url' => route('discussion.index'),
                'badge' => $nonLus,
                'msg_id' => $message->id,
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $message->id,
            'created_at' => $message->created_at->format('H:i'),
        ]);
    }

    /**
     * Envoie une photo dans la discussion. Le fichier est stocké sur le disque
     * public puis référencé par le message envoyé via `send()`.
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('photo');
        $path = $file->store('discussion-photos', 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => $this->photoUrl($path),
        ]);
    }

    /**
     * Envoie une vidéo (et éventuellement sa miniature « poster ») dans la
     * discussion. Les fichiers sont stockés tels quels sur le disque public (dans
     * la limite de taille, aucune compression), puis référencés par `send()`.
     * La miniature est générée côté client (1ère frame) pour servir de vignette
     * iOS : sans poster, Safari affiche un cadre noir.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov,m4v,3gp,mpg,mpeg,avi,mkv', 'max:102400'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $result = ['ok' => true];

        if ($request->hasFile('video')) {
            /** @var UploadedFile $file */
            $file = $request->file('video');
            $result['path'] = $file->store('discussion-videos', 'public');
            $result['url'] = $this->videoUrl($result['path']);
        }

        if ($request->hasFile('poster')) {
            /** @var UploadedFile $file */
            $file = $request->file('poster');
            $result['poster_path'] = $file->store('discussion-video-posters', 'public');
            $result['poster_url'] = $this->photoUrl($result['poster_path']);
        }

        if (empty($result['path']) && empty($result['poster_path'])) {
            return response()->json(['error' => 'Aucun fichier envoyé.'], 422);
        }

        return response()->json($result);
    }

    /**
     * Envoie un message vocal dans la discussion. Le fichier (webm/opus, mp4/aac…)
     * est stocké sur le disque public puis référencé par `send()` via audio_path.
     */
    public function uploadAudio(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:webm,mp4,ogg,oga,m4a,mp3,wav', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('audio');
        $path = $file->store('discussion-audio', 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => $this->audioUrl($path),
        ]);
    }

    /**
     * Modifie le texte d'un message déjà envoyé (façon WhatsApp). Seul
     * l'expéditeur peut éditer, uniquement les messages purement textuels qui ne
     * sont pas supprimés pour tous. La modification est marquée (edited_at) et
     * visible par les deux partenaires lors du prochain poll.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        $message = Message::where('id', $id)->where('couple_id', $couple->id)->first();

        if (! $message) {
            return response()->json(['error' => 'Message introuvable.'], 404);
        }

        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['error' => 'Seul l\'expéditeur peut modifier ce message.'], 403);
        }

        if ($message->isDeletedForAll() || blank($message->body) || $message->isGif() || $message->isPhoto() || $message->isVideo() || $message->isAudio()) {
            return response()->json(['error' => 'Ce message ne peut pas être modifié.'], 422);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // Aucun changement de contenu : on ne marque pas le message comme modifié.
        if ($message->body === $data['body']) {
            return response()->json(['ok' => true, 'id' => $message->id, 'edited' => $message->isEdited()]);
        }

        $message->forceFill([
            'body' => $data['body'],
            'edited_at' => now(),
        ])->save();

        ActivityService::touch($request->user());

        return response()->json([
            'ok' => true,
            'id' => $message->id,
            'body' => $message->body,
            'edited' => true,
        ]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        $message = Message::where('id', $id)->where('couple_id', $couple->id)->first();

        if (! $message) {
            return response()->json(['error' => 'Message introuvable.'], 404);
        }

        $data = $request->validate([
            'mode' => ['required', 'string', 'in:me,all'],
        ]);

        if ($data['mode'] === 'me') {
            MessageDeletion::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
            ]);
        } else {
            // Supprimer pour tous : seul l'expéditeur peut le faire.
            if ($message->sender_id !== $request->user()->id) {
                return response()->json(['error' => 'Seul l\'expéditeur peut supprimer pour tous.'], 403);
            }

            $message->forceFill([
                'deleted_at' => now(),
                'deleted_by' => $request->user()->id,
            ])->save();

            // Retire la notification du message dans la barre du téléphone du
            // partenaire (s'il ne l'a pas encore ouverte) : le SW fermera la
            // notification portant le tag correspondant.
            $partner = $couple->partnerOf($request->user());
            if ($partner) {
                app(PushService::class)->sendToUser($partner, [
                    'type' => 'message_deleted',
                    'msg_id' => $message->id,
                    'url' => route('discussion.index'),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function nonLus(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $count = Message::where('couple_id', $couple->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();

        return response()->json(['nonLus' => $count]);
    }

    protected function marquerLus($couple, $user): void
    {
        Message::where('couple_id', $couple->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Curseur de synchronisation des ✓✓ et des éditions pour un couple : les
     * horodatages serveur les plus récents présents en base.
     *
     * @return array{lus: ?string, modifies: ?string}
     */
    protected function curseurSync($couple, int $userId): array
    {
        $lus = Message::where('couple_id', $couple->id)
            ->where('sender_id', $userId)
            ->whereNotNull('read_at')
            ->max('read_at');

        $modifies = Message::where('couple_id', $couple->id)
            ->whereNotNull('edited_at')
            ->max('edited_at');

        return [
            'lus' => $this->formatCurseur($lus),
            'modifies' => $this->formatCurseur($modifies),
        ];
    }

    /**
     * Format d'échange : UTC, ISO 8601, avec les microsecondes. Les
     * microsecondes sont indispensables car deux événements peuvent tomber dans
     * la même seconde — sans elles, le curseur sauterait le second d'entre-deux.
     */
    protected function formatCurseur($valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        // max() renvoie une valeur brute (une chaîne), pas un Carbon : il faut
        // repasser par l'analyseur de dates.
        return Carbon::parse($valeur)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Curseur reçu du client. Une valeur absente ou illisible ne doit pas casser
     * le poll : elle vaut « aucune borne basse », c'est-à-dire que le delta
     * repart de ce que la base contient réellement.
     */
    protected function parseCurseur($valeur): ?Carbon
    {
        if (! is_string($valeur) || $valeur === '') {
            return null;
        }

        try {
            return Carbon::parse($valeur);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
