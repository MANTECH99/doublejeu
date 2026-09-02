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
    const STATE_URL = '{{ route("discussion.fetch") }}';
    const SEND_URL = '{{ route("discussion.send") }}';
    const MY_ID = {{ $me->id }};

    // Ensemble des id déjà rendus pour éviter tout doublon.
    const renderedIds = new Set();
    let lastMessageId = 0;
    let lastDate = '';
    let sending = false;

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

        const bubble = document.createElement('div');
        bubble.className = 'disc-bubble ' + (isMe ? 'me' : 'them');
        bubble.textContent = msg.body;

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
    }

    function wasAtBottom() {
        return MESSAGES_EL.scrollTop + MESSAGES_EL.clientHeight >= MESSAGES_EL.scrollHeight - 80;
    }

    async function fetchMessages() {
        try {
            const url = STATE_URL + '?after=' + lastMessageId + '&_=' + Date.now();
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;
            const data = await res.json();

            const bottom = wasAtBottom();
            const hasIncoming = (data.messages || []).some(m => String(m.sender_id) !== String(MY_ID));

            if (data.messages && data.messages.length > 0) {
                for (const msg of data.messages) {
                    buildBubble(msg);
                }
                if (bottom || hasIncoming) scrollToBottom(true);
            }

            updateOnline(data.partenaire);
            updateBadge(data.nonLus || 0);
        } catch (e) { /* silencieux */ }
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
                body: JSON.stringify({ body }),
            });

            if (res.ok) {
                const data = await res.json();
                const wasDown = wasAtBottom();
                buildBubble({
                    id: data.id,
                    sender_id: MY_ID,
                    body: body,
                    lu: false,
                    created_at: data.created_at,
                    date: new Date().toISOString().slice(0, 10),
                });
                if (wasDown) scrollToBottom(true);
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
        if (p.enLigne) {
            STATUS_EL.innerHTML = '<span style="color:var(--success)">● en ligne</span>';
        } else if (p.present) {
            STATUS_EL.innerHTML = '<span class="muted">actif·ve il y a ' + p.heure + '</span>';
        } else {
            STATUS_EL.innerHTML = '<span class="disc-offline">hors ligne</span>';
        }
    }

    INPUT_EL.addEventListener('input', () => {
        SEND_BTN.disabled = !INPUT_EL.value.trim();
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
