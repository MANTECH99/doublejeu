<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#E63946">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
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

            /* API pour la présentation de l'accueil : le bouton « Installer » de la motion. */
            window.djInstallPrompt = {
                ready: function () { return !!deferredPrompt && !isStandalone(); },
                /* La bannière masque le bouton de la dernière scène : on la range au lancement. */
                dismiss: function () { dismiss(); },
                prompt: function () {
                    if (isStandalone() || !deferredPrompt) return false;
                    deferredPrompt.prompt();
                    deferredPrompt = null;
                    dismiss();
                    return true;
                }
            };
        })();
    </script>

    <title>@yield('title', 'Double Jeu')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <div class="app-wrap">
        <header class="topbar">
            <a href="/" class="brand">
                <span class="logo">DJ</span>
                <span>Double Jeu</span>
            </a>
            <div class="actions">
                <a href="{{ route('login') }}" class="btn btn-sm btn-soft">Connexion</a>
                <a href="{{ route('register') }}" class="btn btn-sm btn-primary">S'inscrire</a>
            </div>
        </header>
        <main class="fadeIn">
            @yield('content')
        </main>
        <footer class="public-footer">
            <a href="{{ route('info.show', 'confidentialite') }}">Confidentialité</a>
            <a href="{{ route('info.show', 'cgu') }}">CGU</a>
            <a href="{{ route('info.show', 'mentions-legales') }}">Mentions légales</a>
            <a href="{{ route('info.show', 'contact') }}">Contact</a>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>