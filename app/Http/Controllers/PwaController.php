<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        return response(file_get_contents(public_path('manifest.json')), 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function serviceWorker(): Response
    {
        return response(
            <<<'JS'
const CACHE = 'doublejeu-v10';

// Pose le badge sur l'icône de l'app installée (Android via setAppBadge,
// iOS via setNotificationBadge dès que le Web Push arrive).
function setBadge(count) {
    const n = Number(count) || 0;
    if (self.registration.setNotificationBadge) {
        self.registration.setNotificationBadge(n).catch(() => {});
    }
    if (self.registration.setAppBadge) {
        self.registration.setAppBadge(n).catch(() => {});
    }
}
const ASSETS = [
    '/',
    '/manifest.webmanifest',
    '/offscreen',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => Promise.allSettled(ASSETS.map((url) => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) return;

    event.respondWith(
        caches.match(request).then((cached) => {
            const fetched = fetch(request)
                .then((response) => {
                    if (response && response.ok && ['/', '/manifest.webmanifest'].includes(new URL(request.url).pathname)) {
                        const clone = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => cached);

            return cached || fetched;
        })
    );
});

self.addEventListener('push', (event) => {
    let data = { title: 'Double Jeu', body: 'Une notification', url: '/dashboard' };
    if (event.data) {
        try { data = Object.assign({}, data, event.data.json()); } catch (e) {}
    }

    if (data.badge != null) setBadge(data.badge);

    // Suppression « pour tous » : retire la notification du message dans la barre
    // du téléphone (si elle y est encore) au lieu d'en afficher une nouvelle.
    if (data.type === 'message_deleted' && data.msg_id != null) {
        const tagToClose = 'msg-' + data.msg_id;
        event.waitUntil(
            self.registration.getNotifications().then((notifs) => {
                notifs.filter((n) => n.tag === tagToClose).forEach((n) => n.close());
            })
        );
        return;
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            vibrate: [100, 50, 100],
            tag: data.msg_id != null ? 'msg-' + data.msg_id : undefined,
            data: { url: data.url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    setBadge(0);
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if ('focus' in client) { client.navigate(url); return client.focus(); }
                }
                return clients.openWindow(url);
            })
    );
});

self.addEventListener('message', (event) => {
    if (!event.data) return;
    if (event.data.type === 'SKIP_WAITING') self.skipWaiting();
    if (event.data.type === 'SET_BADGE') setBadge(event.data.count);
    if (event.data.type === 'CLEAR_BADGE') setBadge(0);
    // L'utilisateur a lu ses messages : on ferme les notifications visibles de la
    // barre du téléphone et on réinitialise le badge de l'icône de l'app.
    if (event.data.type === 'CLEAR_NOTIFICATIONS') {
        setBadge(0);
        self.registration.getNotifications().then((notifs) => {
            notifs.forEach((n) => n.close());
        });
    }
});
JS
            ,
            200,
            ['Content-Type' => 'application/javascript; charset=UTF-8', 'Service-Worker-Allowed' => '/']
        );
    }

    public function offscreen(): Response
    {
        return response('<html><body></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
