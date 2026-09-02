<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#E63946">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Double Jeu">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

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
<body data-auth="1">
    <div class="app-wrap">

        <header class="topbar">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="logo">DJ</span>
                <span>Double Jeu</span>
            </a>
            <div class="actions">
                <a href="{{ route('discussion.index') }}" class="icon-btn" title="Discussion">💬</a>
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

    @stack('scripts')
</body>
</html>