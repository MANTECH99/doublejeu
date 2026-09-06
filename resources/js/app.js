import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

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
    function tryAutoShowIos() {
        let suppressed = false;
        try { suppressed = localStorage.getItem('dj_ios_install_done') === '1'; } catch (e) {}

        // Ne plus jamais afficher : app déjà installée (standalone),
        // guide déjà montré/fermé, ou on n'est pas sur iOS.
        if (shown || isStandalone() || suppressed || !isIOS()) return;
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
    }

    if (document.readyState === 'complete') {
        setTimeout(tryAutoShowIos, 1500);
    } else {
        window.addEventListener('load', () => setTimeout(tryAutoShowIos, 1500));
    }

    return { dismiss };
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

document.addEventListener('DOMContentLoaded', () => {
    window.notifications.init();
});
