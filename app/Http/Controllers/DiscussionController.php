<?php

namespace App\Http\Controllers;

use App\Models\GifFavorite;
use App\Models\Message;
use App\Models\MessageDeletion;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class DiscussionController extends Controller
{
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
            ->with(['sender:id,name', 'replyTo:id,body,sender_id']);

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
                'is_gif' => $deletedForAll ? false : $m->isGif(),
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
                'heure' => $partenaire?->last_active_at?->diffForHumans(),
                'typing' => $partenaire?->typing_at !== null && $partenaire->typing_at->diffInSeconds() < 3,
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
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        // Un message doit contenir du texte OU un GIF (ou les deux, GIF + légende).
        if (blank($data['body'] ?? null) && blank($data['gif_url'] ?? null)) {
            return response()->json(['error' => 'Le message est vide.'], 422);
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
            'reply_to_id' => $replyToId,
        ]);

        ActivityService::touch($request->user());

        $partner = $couple->partnerOf($request->user());
        if ($partner) {
            $notifBody = ! empty($data['gif_url'] ?? null)
                ? '📷 Envoie un GIF'.($data['body'] ?? '' ? ' : '.$data['body'] : '')
                : ($data['body'] ?? 'Nouveau message');
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
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $message->id,
            'created_at' => $message->created_at->format('H:i'),
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
