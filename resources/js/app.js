import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

import './moodLottie.js';

/* ============ Thème clair / sombre ============ */
(function () {
    const root = document.documentElement;

    function applyTheme(t) {
        if (t === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
        try { localStorage.setItem('dj_theme', t); } catch (e) {}
        document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
            const ico = b.querySelector('.theme-ico') || b;
            ico.textContent = t === 'light' ? '🌙' : '☀️';
        });
    }

    let current = 'dark';
    try { current = localStorage.getItem('dj_theme') || 'dark'; } catch (e) {}

    applyTheme(current);

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        const next = root.hasAttribute('data-theme') ? 'dark' : 'light';
        applyTheme(next);
    });
})();

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/**
 * Helper API : enveloppe fetch avec JSON + CSRF.
 * Retourne {ok, data|error, status}.
 */
window.api = async function api(url, options = {}) {
    const opts = options.json === false
        ? { ...options }
        : {
            method: options.method || 'GET',
            ...(options.body instanceof FormData ? {} : options.body !== undefined ? { body: JSON.stringify(options.body) } : {}),
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.body instanceof FormData || options.json === false ? {} : { 'Content-Type': 'application/json' }),
                ...(options.headers || {}),
            },
        };
    if (options.method) opts.method = options.method;
    if (options.method === undefined && options.body !== undefined) opts.method = 'POST';

    const method = opts.method || 'GET';
    if (method === 'GET') {
        url = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
    }

    try {
        const res = await fetch(url, opts);
        const contentType = res.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await res.json() : await res.text();
        if (!res.ok) {
            const message = typeof data === 'object' && (data.error || data.message)
                ? (data.error || data.message)
                : 'Une erreur est survenue.';
            toast(message, 'error');
            return { ok: false, data, status: res.status };
        }
        return { ok: true, data, status: res.status };
    } catch (e) {
        toast('Connexion impossible. Vérifie ta connexion.', 'error');
        return { ok: false, data: null, status: 0, exception: e };
    }
};

/**
 * Toasts
 */
window.toast = function toast(message, type = 'info', duration = 4200) {
    let box = document.querySelector('.toasts');
    if (!box) {
        box = document.createElement('div');
        box.className = 'toasts';
        document.body.appendChild(box);
    }
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => {
        el.style.transition = 'opacity .3s, transform .3s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-10px)';
        setTimeout(() => el.remove(), 300);
    }, duration);
};

/**
 * Polling conditionné (évite les chevauchements).
 */
window.startPolling = function startPolling(url, handler, { interval = 1800, immediate = true } = {}) {
    let running = false;
    let timer = null;

    const tick = async () => {
        if (running || document.visibilityState === 'hidden') return;
        running = true;
        try {
            const res = await api(url, { json: false });
            if (res.ok) handler(res.data);
        } catch (e) {
            /* silencieux */
        } finally {
            running = false;
        }
    };

    if (immediate) tick();
    timer = setInterval(tick, interval);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') tick();
    });

    return () => clearInterval(timer);
};

/* ============ PWA : service worker + push ============ */

function logPwa(msg, level) {
    (console[level === 'error' ? 'error' : 'warn'] || console.log)('%c[PWA]%c ' + msg, 'color:#E63946;font-weight:bold', 'color:inherit');
}

(async function initPwa() {
    // Service worker
    if ('serviceWorker' in navigator) {
        try {
            const reg = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
            if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
            reg.update(); // toujours reprendre le SW le plus récent
            logPwa('Service worker enregistré (contrôlé : ' + (navigator.serviceWorker.controller ? 'oui' : 'non — 1er rechargement pour prendre le contrôle') + ')');
        } catch (e) {
            logPwa('ÉCHEC enregistrement service worker : ' + e.message + ' — contexte sécurisé : ' + window.isSecureContext, 'error');
        }
    } else {
        logPwa('Service workers non supportés par ce navigateur', 'error');
    }
})();

/* ============ PWA : bannière d'installation ============ */
// La popup d'installation (Android/desktop) est gérée par un <script> synchrone
// dans le <head> (voir layouts/*.blade.php), copié sur AnonGame pour éviter toute
// course d'exécution. Ici on ne gère plus que le guide iOS.

window.installPwa = (function () {
    let shown = false;

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function isIOS() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent);
    }

    function buildDom(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        return tpl.content.firstElementChild;
    }

    function dismiss() {
        const box = document.getElementById('dj-install-box');
        if (box) box.remove();
    }

    // Mémorise que l'utilisateur a déjà installé (ou fermé) le guide iOS :
    // ne plus le réafficher en navigation Safari.
    function suppressIos() {
        try { localStorage.setItem('dj_ios_install_done', '1'); } catch (e) {}
    }

    // iOS ne déclenche jamais beforeinstallprompt : guide vers le menu Partager.
    // `force` = l'utilisateur a cliqué sur « Installer » : on montre le guide même s'il
    // a déjà été fermé ou signalé comme vu.
    function showIosGuide(force) {
        let suppressed = false;
        try { suppressed = localStorage.getItem('dj_ios_install_done') === '1'; } catch (e) {}

        if (isStandalone()) return false;
        if (!force && (shown || suppressed || !isIOS())) return false;
        shown = true;
        const el = buildDom(`
            <div class="dj-install" id="dj-install-box">
                <button class="dj-install-close" data-close aria-label="Fermer">&times;</button>
                <img class="dj-install-logo" src="/icons/icon-192.png" alt="Double Jeu">
                <h4>Double Jeu</h4>
                <p>Installe l'app sur ton iPhone ou iPad pour y jouer d'une simple touche.</p>
                <ol class="dj-install-steps">
                    <li><span>1</span> Touche <b>Partager</b> <em>⎋</em></li>
                    <li><span>2</span> <b>«&nbsp;Sur l'écran d'accueil&nbsp;»</b></li>
                    <li><span>3</span> Puis <b>«&nbsp;Ajouter&nbsp;»</b></li>
                </ol>
                <div class="dj-install-btns">
                    <button class="btn btn-ghost" data-later>Plus tard</button>
                    <button class="btn btn-primary" data-close>Compris</button>
                </div>
            </div>
        `);
        const closeAll = () => { dismiss(); suppressIos(); };
        el.querySelectorAll('[data-close], [data-later]').forEach((b) => b.addEventListener('click', closeAll));
        if (el.querySelector('.dj-install-close')) el.querySelector('.dj-install-close').addEventListener('click', closeAll);
        document.body.appendChild(el);
        return true;
    }

    if (document.readyState === 'complete') {
        setTimeout(() => showIosGuide(false), 1500);
    } else {
        window.addEventListener('load', () => setTimeout(() => showIosGuide(false), 1500));
    }

    return { dismiss, showIosGuide };
})();

/* ============ Push notifications ============ */

window.notifications = {
    subscribed: false,
    async init() {
        this.subscribed = localStorage.getItem('dj_push') === '1';
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        try {
            const reg = await navigator.serviceWorker.ready;
            const existing = await reg.pushManager.getSubscription();
            if (existing) {
                this.subscribed = true;
                localStorage.setItem('dj_push', '1');
            }
        } catch (e) { /* ignore */ }
    },
    async subscribe() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            toast('Notifications supportées uniquement sur HTTPS ou localhost.', 'error');
            return false;
        }
        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY || ''),
            });
            const res = await api('/notifications/subscribe', {
                method: 'POST',
                body: {
                    endpoint: sub.endpoint,
                    keys: {
                        p256dh: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('p256dh')))),
                        auth: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('auth')))),
                    },
                },
            });
            if (res.ok) {
                this.subscribed = true;
                localStorage.setItem('dj_push', '1');
                toast('Notifications activées !', 'success');
                return true;
            }
        } catch (e) {
            toast('Autorisation des notifications refusée.', 'error');
        }
        return false;
    },
};

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}

/* ============ Code PIN ============ */
/* Le jeton d'appareil identifie le compte : l'email n'est ressaisi qu'en cas de perte. */
window.djAppareil = (function () {
    const CLE = 'dj_appareil';

    return {
        lire() {
            try {
                return JSON.parse(window.localStorage.getItem(CLE) || 'null');
            } catch (e) {
                return null;
            }
        },
        ecrire(token, nom) {
            window.localStorage.setItem(CLE, JSON.stringify({ token, nom }));
        },
        oublier() {
            window.localStorage.removeItem(CLE);
        },
    };
})();

/**
 * Pavé numérique : il s'auto-valide à 6 chiffres et renvoie les erreurs sur place.
 */
window.djPin = function djPin(root) {
    const url = root.dataset.pinUrl;
    const points = Array.from(root.querySelectorAll('[data-pin-points] i'));
    const errEl = root.querySelector('[data-pin-err]');
    const compteEl = root.querySelector('[data-pin-compte]');

    let pin = '';
    let occupe = false;
    let appareil = window.djAppareil.lire();

    function montrerCompte() {
        if (!compteEl || !appareil) return;
        compteEl.textContent = 'Compte : ' + (appareil.nom || 'toi');
        compteEl.hidden = false;
    }

    function Peigner() {
        points.forEach((p, i) => p.classList.toggle('on', i < pin.length));
    }

    function dire(message, isErreur) {
        if (errEl) {
            errEl.textContent = message || '';
            errEl.hidden = !message;
        }
        if (isErreur && root.animate) {
            root.animate(
                [
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-9px)' },
                    { transform: 'translateX(9px)' },
                    { transform: 'translateX(0)' },
                ],
                { duration: 260 }
            );
        }
    }

    async function envoyer() {
        if (occupe) return;
        occupe = true;
        dire('');
        root.classList.add('est-occupe');

        const corps = { pin, device_token: appareil ? appareil.token : '' };

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(corps),
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.ok) {
                window.location.href = data.redirect || '/dashboard';
                return;
            }

            const message =
                (data.errors && (data.errors.pin || data.errors.email || []).join(' ')) ||
                data.message ||
                'Code incorrect.';

            pin = '';
            Peigner();
            dire(message, true);
        } catch (e) {
            pin = '';
            Peigner();
            dire('Connexion impossible. Vérifie ta connexion.', true);
        } finally {
            occupe = false;
            root.classList.remove('est-occupe');
        }
    }

    function ajouter(chiffre) {
        if (occupe || pin.length >= 6) return;
        pin += chiffre;
        Peigner();
        if (pin.length === 6) {
            setTimeout(envoyer, 120);
        }
    }

    function retrancher() {
        if (occupe || !pin.length) return;
        pin = pin.slice(0, -1);
        Peigner();
    }

    root.querySelectorAll('[data-pin-key]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const touche = btn.dataset.pinKey;
            if (touche === 'clear') retrancher();
            else if (touche === 'ok') envoyer();
            else ajouter(touche);
        });
    });

    root.addEventListener('keydown', (e) => {
        if (/^\d$/.test(e.key)) ajouter(e.key);
        else if (e.key === 'Backspace') retrancher();
        else if (e.key === 'Enter') envoyer();
    });

    root.tabIndex = -1;
    root.classList.add('est-pret');
    montrerCompte();

    /* Appareil révoqué entre-temps : on le raye avant même de proposer le pavé. */
    if (root.dataset.pinDeviceUrl) {
        fetch(root.dataset.pinDeviceUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ device_token: appareil ? appareil.token : '' }),
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (data && data.ok && data.nom) {
                    if (appareil) appareil.nom = data.nom;
                    montrerCompte();
                    root.hidden = false;
                    window.dispatchEvent(new CustomEvent('dj:pin-pret', { detail: { pret: true } }));
                    return;
                }
                window.djAppareil.oublier();
                root.dispatchEvent(new CustomEvent('dj:pin-perdu'));
            })
            .catch(() => window.dispatchEvent(new CustomEvent('dj:pin-perdu')));
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.notifications.init();
    document.querySelectorAll('[data-pin]').forEach((el) => window.djPin(el));
});
