<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $messages = $messages->map(fn (Message $m) => [
            'id' => $m->id,
            'sender_id' => $m->sender_id,
            'sender_name' => $m->sender?->name ?? 'Ancien·ne partenaire',
            'body' => $m->body,
            'lu' => $m->isRead(),
            'created_at' => $m->created_at->format('H:i'),
            'date' => $m->created_at->format('Y-m-d'),
            'reply_to' => $m->replyTo ? [
                'id' => $m->replyTo->id,
                'sender_id' => $m->replyTo->sender_id,
                'sender_name' => $m->replyTo->sender?->name ?? 'Ancien·ne partenaire',
                'body' => $m->replyTo->body,
            ] : null,
        ]);

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

    public function send(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

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
            'body' => $data['body'],
            'reply_to_id' => $replyToId,
        ]);

        ActivityService::touch($request->user());

        $partner = $couple->partnerOf($request->user());
        if ($partner) {
            app(PushService::class)->sendToUser($partner, [
                'title' => '💬 '.$request->user()->name,
                'body' => mb_strimwidth($data['body'], 0, 80, '…'),
                'url' => route('discussion.index'),
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $message->id,
            'created_at' => $message->created_at->format('H:i'),
        ]);
    }

    public function nonLus(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $count = Message::where('couple_id', $couple->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
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
