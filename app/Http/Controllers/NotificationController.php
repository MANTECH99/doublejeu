<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'keys_public' => $data['keys']['p256dh'] ?? null,
                'keys_auth' => $data['keys']['auth'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::where('endpoint', $data['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request): JsonResponse
    {
        $subscriptions = PushSubscription::where('user_id', $request->user()->id)->get();

        if ($subscriptions->isEmpty()) {
            return response()->json(['ok' => true, 'sent' => 0, 'message' => 'Aucun appareil abonné.']);
        }

        $sent = app(PushService::class)->sendMany(
            $subscriptions,
            ['title' => '🔔 Double Jeu', 'body' => 'Notifications activées avec succès !', 'url' => '/']
        );

        return response()->json(['ok' => true, 'sent' => $sent, 'message' => $sent ? 'Notification envoyée !' : 'Échec de l\'envoi.']);
    }
}
