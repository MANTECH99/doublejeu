<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    private ?WebPush $webPush = null;

    protected function client(): WebPush
    {
        if ($this->webPush) {
            return $this->webPush;
        }

        $auth = [
            'VAPID' => [
                'subject' => env('VAPID_SUBJECT', 'mailto:contact@doublejeu.app'),
                'publicKey' => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ];

        $this->webPush = new WebPush($auth, [], new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
        ]));

        return $this->webPush;
    }

    /**
     * @param  array{title:string, body:string, url:string}|null  $payload
     */
    public function sendToUser(User $user, ?array $payload = null, array $data = []): int
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload ??= ['title' => 'Double Jeu', 'body' => 'Tu as une notification', 'url' => '/dashboard'];

        $this->deferSend($subscriptions, $payload, $data);

        return count($subscriptions);
    }

    /**
     * Envoie les notifications après la réponse HTTP pour ne jamais bloquer la requête.
     */
    private function deferSend(iterable $subscriptions, array $payload, array $data = []): void
    {
        app()->terminating(function () use ($subscriptions, $payload, $data) {
            $this->sendMany($subscriptions, $payload, $data);
        });
    }

    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @param  array{title:string, body:string, url:string}  $payload
     */
    public function sendMany(iterable $subscriptions, array $payload, array $data = []): int
    {
        $webPush = $this->client();
        $sent = 0;

        $json = json_encode(array_merge(['title' => $payload['title'], 'body' => $payload['body'], 'url' => $payload['url']], $data), JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->keys_public,
                        'authToken' => $subscription->keys_auth,
                        'contentEncoding' => 'aesgcm',
                    ]),
                    $json,
                    ['TTL' => 600]
                );
            } catch (\Throwable $e) {
                Log::warning('Push queue error: '.$e->getMessage());
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                $endpoint = $report->getEndpoint();
                PushSubscription::where('endpoint', $endpoint)->delete();
            } else {
                Log::warning('Push failed: '.$report->getReason());
            }
        }

        return $sent;
    }
}
