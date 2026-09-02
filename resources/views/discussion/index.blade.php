@extends('layouts.app')

@section('title', 'Discussion')

@push('head')
<style>
    /* Pas de barre de navigation en bas sur la page discussion :
       le chat occupe tout l'espace jusqu'en bas de l'écran. */
    body .bottom-nav { display: none !important; }
    .disc-wrap { bottom: 0 !important; }
</style>
@endpush
@php
    $me = auth()->user();
    $partenaire = $couple->partnerOf($me);
    $pEnLigne = $partenaire?->last_active_at && $partenaire->last_active_at->diffInMinutes() < 1;
    $pPresent = (bool) $partenaire?->last_active_at;
@endphp

@section('content')
    <div class="disc-wrap">
        {{-- Header --}}
        <div class="disc-header">
            <a href="{{ route('dashboard') }}" class="disc-back">←</a>
            <x-avatar :user="$partenaire" class="sm" />
            <div class="grow">
                <div class="disc-header-name">{{ $partenaire->name }}</div>
                <div class="disc-header-status" id="disc-status">
                    @if ($pEnLigne)
                        <span style="color:var(--success)">● en ligne</span>
                    @elseif ($pPresent)
                        <span class="muted">actif·ve il y a {{ $partenaire->last_active_at->diffForHumans() }}</span>
                    @else
                        <span class="disc-offline">hors ligne</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Messages (occupent l'espace libre entre header et composer) --}}
        <div class="disc-messages" id="disc-messages">
            <div class="disc-welcome">
                <div style="font-size:36px; margin-bottom:8px">💬</div>
                <div class="tiny muted">Vos messages sont privés.<br>Discutez de tout, à tout moment.</div>
            </div>
        </div>

        {{-- Barre "répondre à…" (affichée quand on répond à un message) --}}
        <div class="disc-replybar" id="disc-replybar" style="display:none">
            <div class="disc-reply-info">
                <div class="disc-reply-name" id="disc-reply-name">Répondre</div>
                <div class="disc-reply-body" id="disc-reply-body"></div>
            </div>
            <button class="disc-reply-close" id="disc-reply-close" aria-label="Annuler la réponse">✕</button>
        </div>

        {{-- Composer (fixe, juste au-dessus de la barre de navigation) --}}
        <div class="disc-composer" id="disc-composer">
            <input
                type="text"
                id="disc-input"
                class="disc-input"
                placeholder="Écrire un message…"
                maxlength="2000"
                autocomplete="off"
            />
            <button class="disc-send" id="disc-send" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const MESSAGES_EL = document.getElementById('disc-messages');
    const INPUT_EL = document.getElementById('disc-input');
    const SEND_BTN = document.getElementById('disc-send');
    const STATUS_EL = document.getElementById('disc-status');
    const REPLYBAR_EL = document.getElementById('disc-replybar');
    const REPLY_NAME_EL = document.getElementById('disc-reply-name');
    const REPLY_BODY_EL = document.getElementById('disc-reply-body');
    const REPLY_CLOSE_EL = document.getElementById('disc-reply-close');
    const STATE_URL = '{{ route("discussion.fetch") }}';
    const SEND_URL = '{{ route("discussion.send") }}';
    const TYPING_URL = '{{ route("discussion.typing") }}';
    const MY_ID = {{ $me->id }};
    const PARTNER_NAME = @json($partenaire->name);

    // Ensemble des id déjà rendus pour éviter tout doublon.
    const renderedIds = new Set();
    let lastMessageId = 0;
    let lastDate = '';
    let sending = false;
    let replyTarget = null; // {id, sender_name, body}

    function scrollToBottom(smooth) {
        if (smooth) {
            MESSAGES_EL.scrollTo({ top: MESSAGES_EL.scrollHeight, behavior: 'smooth' });
        } else {
            MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        }
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (d.toDateString() === today.toDateString()) return 'Aujourd\'hui';
        if (d.toDateString() === yesterday.toDateString()) return 'Hier';
        return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    }

    function escHtml(s) {
        const el = document.createElement('div');
        el.textContent = s;
        return el.innerHTML;
    }

    /* ---------- Répondre à un message ---------- */
    function setReply(msg) {
        replyTarget = msg ? { id: msg.id, sender_name: msg.sender_name, body: msg.body } : null;
        if (replyTarget) {
            REPLY_NAME_EL.textContent = '↩️ Répondre à ' + (replyTarget.sender_name || '…');
            REPLY_BODY_EL.textContent = replyTarget.body;
            REPLYBAR_EL.style.display = 'flex';
            INPUT_EL.focus();
        } else {
            REPLYBAR_EL.style.display = 'none';
        }
    }
    REPLY_CLOSE_EL.addEventListener('click', () => setReply(null));

    function openActionMenu(msg, e) {
        const wrap = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + msg.id + '"]');

        // Fond sombre + flou derrière, seul le message sélectionné reste net.
        const backdrop = document.createElement('div');
        backdrop.className = 'disc-backdrop';
        backdrop.addEventListener('click', removeActionMenu);
        backdrop.addEventListener('touchmove', (ev) => ev.preventDefault(), { passive: false });
        document.body.appendChild(backdrop);

        if (wrap) {
            wrap.classList.add('disc-selected');
            wrap.style.zIndex = '70';
        }
        MESSAGES_EL.classList.add('disc-overlay');

        const menu = document.createElement('div');
        menu.className = 'disc-action-menu';
        const reply = document.createElement('button');
        reply.className = 'disc-action-btn';
        reply.textContent = '↩️ Répondre';
        reply.addEventListener('click', () => { setReply(msg); removeActionMenu(); });
        menu.appendChild(reply);
        const close = document.createElement('button');
        close.className = 'disc-action-close';
        close.textContent = 'Annuler';
        close.addEventListener('click', removeActionMenu);
        menu.appendChild(close);
        document.body.appendChild(menu);

        // Place le menu juste sous la bulle sélectionnée (comme WhatsApp).
        placeMenuBelow(menu, wrap, msg);
    }

    function placeMenuBelow(menu, wrap, msg) {
        const menuW = 220;
        const menuH = 120; // hauteur approximative du menu
        const gap = 6;

        let top, left;
        if (wrap) {
            const rect = wrap.getBoundingClientRect();
            // Collé juste sous la bulle (en restant dans l'écran en bas).
            top = Math.min(rect.bottom + gap, window.innerHeight - menuH - 12);
            top = Math.max(top, 12);
            // Aligné à gauche de la bulle, sans déborder à droite.
            left = Math.min(Math.max(rect.left, 12), window.innerWidth - menuW - 12);
        } else {
            top = window.innerHeight / 2;
            left = (window.innerWidth - menuW) / 2;
        }

        menu.style.top = Math.round(top) + 'px';
        menu.style.left = Math.round(left) + 'px';
        menu.style.transform = 'translateY(0)';
        menu.style.width = menuW + 'px';
    }

    function removeActionMenu() {
        document.querySelectorAll('.disc-backdrop').forEach(el => el.remove());
        document.querySelectorAll('.disc-action-menu').forEach(el => el.remove());
        MESSAGES_EL.querySelectorAll('.disc-bubble-wrap.disc-selected').forEach(el => {
            el.classList.remove('disc-selected');
            el.style.zIndex = '';
        });
        MESSAGES_EL.classList.remove('disc-overlay');
    }

    function buildBubble(msg) {
        if (renderedIds.has(msg.id)) {
            return;
        }
        renderedIds.add(msg.id);
        if (msg.id > lastMessageId) lastMessageId = msg.id;

        const isMe = String(msg.sender_id) === String(MY_ID);

        // Retire le bloc d'accueil dès le premier message rendu.
        const welcome = MESSAGES_EL.querySelector('.disc-welcome');
        if (welcome) welcome.remove();

        if (msg.date !== lastDate) {
            lastDate = msg.date;
            const sep = document.createElement('div');
            sep.className = 'disc-date-sep';
            sep.innerHTML = '<span>' + escHtml(formatDate(msg.date)) + '</span>';
            MESSAGES_EL.appendChild(sep);
        }

        const wrap = document.createElement('div');
        wrap.className = 'disc-bubble-wrap ' + (isMe ? 'me' : 'them');
        wrap.dataset.id = msg.id;

        const bubble = document.createElement('div');
        bubble.className = 'disc-bubble ' + (isMe ? 'me' : 'them');

        // Message cité (réponse) au-dessus du texte.
        if (msg.reply_to) {
            const quoted = document.createElement('div');
            quoted.className = 'disc-quoted';
            const qName = document.createElement('div');
            qName.className = 'disc-quoted-name';
            const meSaid = String(msg.reply_to.sender_id) === String(MY_ID);
            const who = meSaid ? 'Toi' : (msg.reply_to.sender_name || '…');
            qName.textContent = '↪️ ' + who + ' : ' + escHtml(msg.reply_to.body);
            quoted.appendChild(qName);
            bubble.appendChild(quoted);
        }

        const bodyText = document.createElement('span');
        bodyText.className = 'disc-bubble-text';
        bodyText.textContent = msg.body;
        bubble.appendChild(bodyText);

        const meta = document.createElement('div');
        meta.className = 'disc-meta ' + (isMe ? 'me' : 'them');

        const time = document.createElement('span');
        time.className = 'disc-time';
        time.textContent = msg.created_at;
        meta.appendChild(time);

        if (isMe) {
            const check = document.createElement('span');
            check.className = 'disc-check';
            check.textContent = msg.lu ? '✓✓' : '✓';
            if (msg.lu) check.classList.add('lu');
            meta.appendChild(check);
        }

        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        MESSAGES_EL.appendChild(wrap);

        // Clic long / menu contextuel / swipe → répondre.
        wrap.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            openActionMenu(msg, e);
        });
        attachSwipeReply(wrap, msg);
    }

    /* Swipe vers la droite → répondre directement à ce message. */
    function attachSwipeReply(wrap, msg) {
        let startX = null;
        let startY = null;
        let touchTimer = null;
        let longPressFired = false;

        wrap.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            longPressFired = false;
            clearTimeout(touchTimer);
            touchTimer = setTimeout(() => { longPressFired = true; openActionMenu(msg); }, 500);
        }, { passive: true });
        wrap.addEventListener('touchend', (e) => {
            clearTimeout(touchTimer);
            if (startX === null || longPressFired) { startX = null; return; }
            const touch = e.changedTouches[0];
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            // Déplacement net vers la droite, très peu vertical.
            if (dx > 60 && Math.abs(dy) < 40) setReply(msg);
            startX = null;
        }, { passive: true });
        wrap.addEventListener('touchcancel', () => { clearTimeout(touchTimer); startX = null; });
    }

    // Met à jour le statut "lu" (✓✓) des bulles déjà affichées.
    function syncReadState(messages) {
        for (const msg of messages) {
            if (!renderedIds.has(msg.id)) continue;
            const wrap = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + msg.id + '"]');
            if (!wrap) continue;
            const check = wrap.querySelector('.disc-check');
            if (!check) continue;
            const isLu = !!msg.lu;
            check.textContent = isLu ? '✓✓' : '✓';
            check.classList.toggle('lu', isLu);
        }
    }

    function wasAtBottom() {
        return MESSAGES_EL.scrollTop + MESSAGES_EL.clientHeight >= MESSAGES_EL.scrollHeight - 80;
    }

    async function fetchMessages() {
        // Le refresh (?) force le navigateur à recharger les données, utile pour
        // diagnostiquer un affichage vide après un déploiement.
        const hard = new URLSearchParams(location.search).has('refresh');
        try {
            // On récupère les derniers messages à chaque poll : cela permet aussi
            // de rafraîchir le statut "lu" (✓✓) des bulles déjà affichées.
            const url = STATE_URL + '?after=0&_=' + Date.now();
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) {
                if (hard) console.error('discussion:fetch HTTP', res.status, res.statusText);
                return;
            }
            let data;
            try {
                data = await res.json();
            } catch (jsonErr) {
                if (hard) console.error('discussion:fetch json', jsonErr);
                return;
            }

            const bottom = wasAtBottom();
            const hasIncoming = (data.messages || []).some(m => String(m.sender_id) !== String(MY_ID) && !renderedIds.has(m.id));

            if (data.messages && data.messages.length > 0) {
                for (const msg of data.messages) {
                    buildBubble(msg);
                }
                syncReadState(data.messages);
                if (bottom || hasIncoming) scrollToBottom(true);
            }

            updateOnline(data.partenaire);
            updateBadge(data.nonLus || 0);
            if (hard && (!data.messages || data.messages.length === 0)) {
                console.warn('discussion:fetch OK mais aucun message dans la réponse');
            }
        } catch (e) {
            if (hard) console.error('discussion:fetch exception', e);
        }
    }

    function updateBadge(count) {
        let badge = document.getElementById('disc-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'disc-badge';
                badge.className = 'disc-badge';
                document.querySelector('.disc-header')?.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }

    async function sendMessage() {
        const body = INPUT_EL.value.trim();
        if (!body || sending) return;

        sending = true;
        SEND_BTN.disabled = true;

        try {
            const res = await fetch(SEND_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ body, reply_to_id: replyTarget ? replyTarget.id : null }),
            });

            if (res.ok) {
                const data = await res.json();
                const replyingId = replyTarget ? replyTarget.id : null;
                const wasDown = wasAtBottom();
                buildBubble({
                    id: data.id,
                    sender_id: MY_ID,
                    body: body,
                    lu: false,
                    created_at: data.created_at,
                    date: new Date().toISOString().slice(0, 10),
                    reply_to: replyingId ? {
                        id: replyingId,
                        sender_id: null,
                        sender_name: replyTarget.sender_name,
                        body: replyTarget.body,
                    } : null,
                });
                if (wasDown) scrollToBottom(true);
                setReply(null);
                INPUT_EL.value = '';
                SEND_BTN.disabled = true;
            } else {
                toast('Erreur lors de l\'envoi.', 'error');
            }
        } catch (e) {
            toast('Connexion perdue.', 'error');
        } finally {
            sending = false;
            SEND_BTN.disabled = !INPUT_EL.value.trim();
        }
    }

    function updateOnline(p) {
        if (!p) return;
        if (p.typing) {
            STATUS_EL.innerHTML = '<span style="color:var(--primary)">en train d\u2019\u00e9crire…</span>';
        } else if (p.enLigne) {
            STATUS_EL.innerHTML = '<span style="color:var(--success)">● en ligne</span>';
        } else if (p.present) {
            STATUS_EL.innerHTML = '<span class="muted">actif·ve il y a ' + p.heure + '</span>';
        } else {
            STATUS_EL.innerHTML = '<span class="disc-offline">hors ligne</span>';
        }
    }

    // Envoie le signal "je tape" au maximum une fois par période de poll (1,5 s) :
    // le timestamp typing_at reste ainsi frais tant qu'on écrit.
    let lastTypingSent = 0;
    function sendTyping() {
        const now = Date.now();
        if (now - lastTypingSent < 1200) return;
        lastTypingSent = now;
        fetch(TYPING_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }).catch(() => {});
    }

    INPUT_EL.addEventListener('input', () => {
        SEND_BTN.disabled = !INPUT_EL.value.trim();
        if (INPUT_EL.value.trim()) sendTyping();
    });
    INPUT_EL.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    SEND_BTN.addEventListener('click', sendMessage);

    // Poll unique : messages + statut en ligne en une seule requête (1,5 s).
    fetchMessages();
    setInterval(fetchMessages, 1500);

    INPUT_EL.focus();
})();
</script>
@endpush
