<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Thème (sombre / rose / blanc) appliqué avant le premier rendu, pour
        // éviter le flash de la palette par défaut. 'dark' reste l'absence
        // d'attribut : c'est le thème par défaut du design system.
        (function () {
            try {
                var t = localStorage.getItem('dj_theme');
                if (t === 'light' || t === 'rose') {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) {}
        })();
    </script>
    <meta name="theme-color" content="#E63946">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Double Jeu">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    {{-- Ecran de demarrage iOS (equivalent du splash Android) : logo centre sur #121212, taille exacte par appareil. --}}
    <link rel="apple-touch-startup-image" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-640x1136.png">
    <link rel="apple-touch-startup-image" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-1136x640.png">
    <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-750x1334.png">
    <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-1334x750.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1242x2208.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2208x1242.png">
    <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1125x2436.png">
    <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2436x1125.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-828x1792.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-1792x828.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1242x2688.png">
    <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2688x1242.png">
    <link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1170x2532.png">
    <link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2532x1170.png">
    <link rel="apple-touch-startup-image" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1284x2778.png">
    <link rel="apple-touch-startup-image" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2778x1284.png">
    <link rel="apple-touch-startup-image" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1179x2556.png">
    <link rel="apple-touch-startup-image" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2556x1179.png">
    <link rel="apple-touch-startup-image" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1290x2796.png">
    <link rel="apple-touch-startup-image" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2796x1290.png">
    <link rel="apple-touch-startup-image" media="(device-width: 402px) and (device-height: 874px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1206x2622.png">
    <link rel="apple-touch-startup-image" media="(device-width: 402px) and (device-height: 874px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2622x1206.png">
    <link rel="apple-touch-startup-image" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="/splash/startup-1320x2868.png">
    <link rel="apple-touch-startup-image" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)" href="/splash/startup-2868x1320.png">
    <link rel="apple-touch-startup-image" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-1536x2048.png">
    <link rel="apple-touch-startup-image" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2048x1536.png">
    <link rel="apple-touch-startup-image" media="(device-width: 810px) and (device-height: 1080px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-1620x2160.png">
    <link rel="apple-touch-startup-image" media="(device-width: 810px) and (device-height: 1080px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2160x1620.png">
    <link rel="apple-touch-startup-image" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-1668x2224.png">
    <link rel="apple-touch-startup-image" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2224x1668.png">
    <link rel="apple-touch-startup-image" media="(device-width: 820px) and (device-height: 1180px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-1640x2360.png">
    <link rel="apple-touch-startup-image" media="(device-width: 820px) and (device-height: 1180px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2360x1640.png">
    <link rel="apple-touch-startup-image" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-1668x2388.png">
    <link rel="apple-touch-startup-image" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2388x1668.png">
    <link rel="apple-touch-startup-image" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="/splash/startup-2048x2732.png">
    <link rel="apple-touch-startup-image" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)" href="/splash/startup-2732x2048.png">

    <script>
        // PWA — tout dans le <head> synchrone (comme AnonGame) : aucune race, fiable tôt.
        (function () {
            var deferredPrompt = null;
            var shown = false;
            function isStandalone() {
                return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
            }
            function isIOS() { return /iphone|ipad|ipod/i.test(navigator.userAgent); }
            function dismiss() {
                var box = document.getElementById('dj-install-box');
                if (box) { box.remove(); }
            }
            function makeBtn(label, cls, handler) {
                var b = document.createElement('button');
                b.className = cls;
                b.textContent = label;
                b.addEventListener('click', handler);
                return b;
            }
            function buildPopup() {
                if (shown || isStandalone()) return;
                shown = true;
                var el = document.createElement('div');
                el.className = 'dj-install';
                el.id = 'dj-install-box';
                var close = document.createElement('button');
                close.className = 'dj-install-close';
                close.setAttribute('aria-label', 'Fermer');
                close.innerHTML = '&times;';
                close.addEventListener('click', dismiss);
                var logo = document.createElement('img');
                logo.className = 'dj-install-logo';
                logo.src = '/icons/icon-192.png';
                logo.alt = 'Double Jeu';
                var h4 = document.createElement('h4');
                h4.textContent = 'Double Jeu';
                var p = document.createElement('p');
                p.textContent = "Installe l'app pour y jouer d'une simple touche, même hors ligne.";
                var btns = document.createElement('div');
                btns.className = 'dj-install-btns';
                btns.appendChild(makeBtn('Plus tard', 'btn btn-ghost', dismiss));
                btns.appendChild(makeBtn('Installer', 'btn btn-primary', function () {
                    if (!deferredPrompt) { dismiss(); return; }
                    deferredPrompt.prompt();
                    deferredPrompt = null;
                    dismiss();
                }));
                el.appendChild(close);
                el.appendChild(logo);
                el.appendChild(h4);
                el.appendChild(p);
                el.appendChild(btns);
                document.body.appendChild(el);
            }
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferredPrompt = e;
                buildPopup();
            });
            window.addEventListener('appinstalled', function () { shown = true; dismiss(); });
        })();
    </script>

    <title>@yield('title', config('app.name', 'Double Jeu'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')

    <script>
        window.VAPID_PUBLIC_KEY = @json(config('services.webpush.public_key', ''));
    </script>
</head>
<body data-auth="1" data-tz="{{ Auth::user()?->timezone ?? '' }}" data-linked="{{ optional(Auth::user())->couple_id ? '1' : '0' }}">
    <div class="app-wrap">

        <header class="topbar">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="logo">DJ</span>
                <span>Double Jeu</span>
            </a>
            <div class="actions">
                <a href="{{ route('discussion.index') }}" class="icon-btn" title="Discussion">💬<span class="dj-badge" id="header-disc-badge" style="display:none"></span></a>
                <a href="{{ route('recompenses.index') }}" class="icon-btn" title="Récompenses">🏆</a>
                <a href="{{ route('cartes.index') }}" class="icon-btn" title="Mes cartes">🃏</a>
                <a href="{{ route('profile.edit') }}" class="icon-btn" title="Profil">👤</a>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <x-nav-bar />
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if (session('flash'))
                toast(@json(session('flash')['message']), @json(session('flash')['type'] ?? 'info'));
                @php(session()->forget('flash'))
            @endif

            @foreach ($errors->all() as $error)
                toast(@json($error), 'error');
            @endforeach
        });
    </script>

    <script>
        // Compteur de messages non lus (header + nav + badge PWA), comme WhatsApp.
        (function () {
            if (!document.body || document.body.getAttribute('data-auth') !== '1') return;

            function setBadge(el, n) {
                if (!el) return;
                if (n > 0) {
                    el.textContent = n > 99 ? '99+' : n;
                    el.style.display = 'grid';
                } else {
                    el.style.display = 'none';
                }
            }

            function updateBadges(n) {
                setBadge(document.getElementById('header-disc-badge'), n);
                setBadge(document.getElementById('nav-disc-badge'), n);
                if ('setAppBadge' in navigator) {
                    navigator.setAppBadge(n).catch(function () {});
                }
                // iOS ne connaît pas setAppBadge : on passe par le service worker,
                // qui gère setNotificationBadge et setAppBadge côté registration.
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.ready
                        .then(function (reg) {
                            var active = reg.active || reg.waiting;
                            if (active) active.postMessage({ type: 'SET_BADGE', count: n });
                        })
                        .catch(function () {});
                }
            }

            async function poll() {
                try {
                    var res = await fetch('/discussion/non-lus', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store'
                    });
                    if (res.ok) {
                        var data = await res.json();
                        updateBadges(data.nonLus || 0);
                    }
                } catch (e) {}
            }

            poll();
            setInterval(poll, 1000);

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') poll();
            });
        })();
    </script>

    <script>
        // Fuseau horaire : auto-détection envoyée au profil (met le 00h/20h des missions au fuseau du partenaire).
        (function () {
            if (document.body.getAttribute('data-auth') !== '1') return;
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (tz && tz !== document.body.getAttribute('data-tz') && tz !== localStorage.getItem('dj_tz_sent')) {
                    fetch('/profile/timezone', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ timezone: tz })
                    }).catch(function () {});
                    try { localStorage.setItem('dj_tz_sent', tz); } catch (e) {}
                }
            } catch (e) {}
        })();

        // Un seul modal à la fois, sur toute l'app. Les modals rendus par la
        // page (tutoriel anniversaire, Octobre rose…) et les popups asynchrones
        // (« question du soir » de 20h) passent tous par cette fonction : le
        // rappel attend qu'aucun .modal-ov ne soit plus visible.
        window.djModalLibre = function (rappel) {
            var attendre = function () {
                var occupe = Array.prototype.some.call(
                    document.querySelectorAll('.modal-ov'),
                    function (el) { return el.style.display === 'flex'; }
                );

                if (occupe) {
                    setTimeout(attendre, 300);
                    return;
                }

                rappel();
            };

            attendre();
        };

        // Infos missions : popup persistante « nouvelle mission » / « question du soir » (D'accord = vu).
        (function () {
            var queued = [];
            var showing = false;
            var csrf = function () {
                var m = document.querySelector('meta[name="csrf-token"]');
                return m ? m.getAttribute('content') : '';
            };

            function showNext() {
                if (showing || queued.length === 0) return;
                showing = true;
                var info = queued.shift();
                var ov = document.createElement('div');
                ov.className = 'modal-ov';
                ov.style.display = 'flex';
                var card = document.createElement('div');
                card.className = 'modal';
                card.style.textAlign = 'center';
                var emoji = document.createElement('div');
                emoji.style.fontSize = '40px';
                emoji.textContent = info.type === 'mission' ? '🕵️' : (info.type === 'verdict' ? '🏆' : '🌙');
                var h3 = document.createElement('h3');
                h3.style.margin = '10px 0 8px';
                h3.style.fontSize = '18px';
                h3.textContent = info.title;
                var p = document.createElement('p');
                p.className = 'tiny muted';
                p.style.lineHeight = '1.5';
                p.textContent = info.message;
                var btn = document.createElement('button');
                btn.className = 'btn btn-primary btn-block mt16';
                btn.textContent = 'D\'accord';
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.textContent = '…';
                    var url = info.type === 'verdict'
                        ? '/jeux/mission-secrete/verdict-vu'
                        : '/jeux/mission-secrete/' + info.mission_id + '/vu';
                    var body = info.type === 'verdict'
                        ? {}
                        : { role: info.type === 'mission' ? 'cible' : 'partenaire' };
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(body)
                    }).catch(function () {}).finally(function () {
                        ov.remove();
                        showing = false;
                        if (queued.length) showNext();
                    });
                });
                card.appendChild(emoji);
                card.appendChild(h3);
                card.appendChild(p);
                card.appendChild(btn);
                ov.appendChild(card);
                document.body.appendChild(ov);
            }

            function kick() {
                if (!queued.length) return;
                // Si un modal de la page est déjà ouvert (anniversaire, Octobre
                // rose…), la popup attend qu'il soit refermé.
                if (window.djModalLibre) window.djModalLibre(showNext);
                else setTimeout(showNext, 600);
            }

            if (document.body.getAttribute('data-auth') === '1' && document.body.getAttribute('data-linked') === '1') {
                fetch('/jeux/mission-secrete/infos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                }).then(function (res) {
                    if (!res.ok) return null;
                    return res.json();
                }).then(function (data) {
                    if (data && Array.isArray(data.modals) && data.modals.length) {
                        queued = data.modals;
                        kick();
                    }
                }).catch(function () {});
            }
        })();
    </script>

    <script>
        // Formulaires longs (vidéo, son, photos) : on affiche l'état d'envoi,
        // sinon le bouton semble inerte pendant l'upload.
        document.querySelectorAll('form[data-busy]').forEach(function (form) {
            form.addEventListener('submit', function () {
                const bouton = form.querySelector('[type="submit"]');
                if (!bouton || bouton.dataset.busyDone) return;

                bouton.dataset.busyDone = '1';
                bouton.setAttribute('aria-busy', 'true');
                bouton.classList.add('is-busy');
                bouton.disabled = true;
                bouton.innerHTML = '<span class="spinner spinner-sm"></span><span>'
                    + (form.dataset.busyLabel || 'Envoi en cours…') + '</span>';
            });
        });
    </script>

    @stack('scripts')
</body>
</html>