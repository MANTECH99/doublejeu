<?php

namespace App\Http\Controllers;

use App\Models\GifFavorite;
use App\Models\Message;
use App\Models\MessageDeletion;
use App\Services\ActivityService;
use App\Services\PushService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DiscussionController extends Controller
{
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

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $partner = $couple->partnerOf(Auth::user());

        $this->marquerLus($couple, Auth::user());

        return view('discussion.index', [
            'couple' => $couple,
            'partner' => $partner,
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        // Être dans la discussion = être en ligne.
        ActivityService::touch($request->user());

        $this->marquerLus($couple, $request->user());

        $apresId = (int) $request->query('after', 0);

        $query = Message::where('couple_id', $couple->id)
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['sender:id,name,avatar_url', 'replyTo:id,body,sender_id']);

        if ($apresId > 0) {
            // Poll incrémental : seuls les nouveaux messages depuis le dernier id.
            $messages = (clone $query)
                ->where('id', '>', $apresId)
                ->orderBy('id')
                ->get();
        } else {
            // Chargement initial : tout l'historique, du plus ancien au plus récent.
            $messages = (clone $query)
                ->orderByDesc('id')
                ->get()
                ->reverse()
                ->values();
        }

        $userId = $request->user()->id;
        $messages = $messages->map(function (Message $m) use ($userId) {
            $deletedForAll = $m->isDeletedForAll();

            return [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'sender_name' => $m->sender?->name ?? 'Ancien·ne partenaire',
                'body' => $deletedForAll ? null : $m->body,
                'gif_url' => $deletedForAll ? null : $m->gif_url,
                'gif_alt' => $deletedForAll ? null : $m->gif_alt,
                'photo_url' => $deletedForAll ? null : $this->photoUrl($m->photo_path),
                'audio_url' => $deletedForAll ? null : $this->audioUrl($m->audio_path),
                'audio_duration' => $deletedForAll ? null : $m->audio_duration,
                'audio_bars' => $deletedForAll ? null : $m->audio_bars,
                'is_gif' => $deletedForAll ? false : $m->isGif(),
                'is_photo' => $deletedForAll ? false : $m->isPhoto(),
                'is_audio' => $deletedForAll ? false : $m->isAudio(),
                // URL racine-relative de la photo de profil de l'expéditeur :
                // fonctionne depuis tout appareil du couple même si APP_URL diffère.
                'sender_photo_url' => $m->sender?->avatar_url ? '/storage/'.$m->sender->avatar_url : null,
                'lu' => $m->isRead(),
                'deleted_for_all' => $deletedForAll,
                'deleted_by_me' => $deletedForAll && $m->deleted_by === $userId,
                'created_at' => $m->created_at->format('H:i'),
                'date' => $m->created_at->format('Y-m-d'),
                'reply_to' => $m->replyTo && ! $deletedForAll ? [
                    'id' => $m->replyTo->id,
                    'sender_id' => $m->replyTo->sender_id,
                    'sender_name' => $m->replyTo->sender?->name ?? 'Ancien·ne partenaire',
                    'body' => $m->replyTo->body,
                ] : null,
            ];
        });

        $nonLus = Message::where('couple_id', $couple->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        $partenaire = $couple->partnerOf($request->user())?->fresh();

        return response()->json([
            'messages' => $messages,
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
            'audio_path' => ['nullable', 'string', 'max:255'],
            'audio_duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'audio_bars' => ['nullable', 'string', 'max:2048'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        // Un message doit contenir du texte, un GIF, une photo ou un vocal.
        if (blank($data['body'] ?? null) && blank($data['gif_url'] ?? null) && blank($data['photo_path'] ?? null) && blank($data['audio_path'] ?? null)) {
            return response()->json(['error' => 'Le message est vide.'], 422);
        }

        // Vérifier que le fichier existe sur le disque public.
        if (! blank($data['photo_path'] ?? null) && ! Storage::disk('public')->exists($data['photo_path'])) {
            return response()->json(['error' => 'Photo introuvable.'], 422);
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
                    : (! empty($data['gif_url'] ?? null)
                        ? '📷 Envoie un GIF'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                        : ($data['body'] ?? 'Nouveau message')));
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
}
