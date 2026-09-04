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

        {{-- Barre de sélection multi-messages (remplace le header en mode sélection) --}}
        <div class="disc-selectionbar" id="disc-selectionbar" style="display:none">
            <button class="disc-down-btn" id="disc-sel-back" aria-label="Fermer la sélection">←</button>
            <div class="disc-sel-count" id="disc-sel-count">1</div>
            <div class="grow"></div>
            <button class="disc-sel-action" id="disc-sel-reply" aria-label="Répondre" title="Répondre">↩️</button>
            <button class="disc-sel-action" id="disc-sel-star" aria-label="Etoile" title="Favori" style="display:none">⭐</button>
            <button class="disc-sel-action disc-sel-delete" id="disc-sel-delete" aria-label="Supprimer" title="Supprimer">🗑️</button>
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
            <button class="disc-gif-btn" id="disc-gif-btn" type="button" aria-label="Envoyer un GIF">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M10 12h2v3"></path>
                    <path d="M7 9h2"></path>
                    <path d="M13 9v3c0 1.1.9 2 2 2"></path>
                </svg>
            </button>
            <textarea
                id="disc-input"
                class="disc-input"
                rows="1"
                placeholder="Écrire un message…"
                maxlength="2000"
                autocomplete="off"
                enterkeyhint="newline"
            ></textarea>
            <button class="disc-send" id="disc-send" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>

    {{-- Panneau GIF (recherche GIPHY + favoris), au-dessus du composer --}}
    <div class="disc-gifpanel" id="disc-gifpanel" style="display:none">
        <div class="disc-gifpanel-head">
            <input type="text" id="disc-gif-search" class="disc-gif-search" placeholder="Rechercher un GIF… " />
            <button class="disc-gif-close" id="disc-gif-close" aria-label="Fermer">✕</button>
        </div>
        <div class="disc-gif-tabs">
            <button type="button" class="disc-gif-tab active" data-tab="search" id="disc-tab-search">Recherche</button>
            <button type="button" class="disc-gif-tab" data-tab="favs" id="disc-tab-favs">Favoris</button>
        </div>
        <div class="disc-gif-grid" id="disc-gif-grid"></div>
    </div>

    {{-- Bottom-sheet : options de suppression (style WhatsApp) --}}
    <div class="disc-sheet-backdrop" id="disc-sheet-backdrop" style="display:none"></div>
    <div class="disc-sheet" id="disc-sheet" style="display:none">
        <div class="disc-sheet-title" id="disc-sheet-title">Supprimer</div>
        <button type="button" class="disc-sheet-btn disc-sheet-delete-red" id="disc-sheet-delete-me">🗑️ Supprimer pour moi</button>
        <button type="button" class="disc-sheet-btn disc-sheet-delete-red" id="disc-sheet-delete-all" style="display:none">🗑️ Supprimer pour tous</button>
        <button type="button" class="disc-sheet-btn disc-sheet-cancel" id="disc-sheet-cancel">Annuler</button>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const MESSAGES_EL = document.getElementById('disc-messages');
    const INPUT_EL = document.getElementById('disc-input');
    const SEND_BTN = document.getElementById('disc-send');
    const STATUS_EL = document.getElementById('disc-status');
    const GIF_BTN = document.getElementById('disc-gif-btn');
    const GIF_PANEL = document.getElementById('disc-gifpanel');
    const GIF_SEARCH = document.getElementById('disc-gif-search');
    const GIF_GRID = document.getElementById('disc-gif-grid');
    const GIF_CLOSE = document.getElementById('disc-gif-close');
    const TAB_SEARCH = document.getElementById('disc-tab-search');
    const TAB_FAVS = document.getElementById('disc-tab-favs');
    const REPLYBAR_EL = document.getElementById('disc-replybar');
    const REPLY_NAME_EL = document.getElementById('disc-reply-name');
    const REPLY_BODY_EL = document.getElementById('disc-reply-body');
    const REPLY_CLOSE_EL = document.getElementById('disc-reply-close');
    const SEL_BAR = document.getElementById('disc-selectionbar');
    const SEL_BACK = document.getElementById('disc-sel-back');
    const SEL_COUNT = document.getElementById('disc-sel-count');
    const SEL_REPLY = document.getElementById('disc-sel-reply');
    const SEL_STAR = document.getElementById('disc-sel-star');
    const SEL_DELETE = document.getElementById('disc-sel-delete');
    const DISC_HEADER = document.querySelector('.disc-header');
    const COMPOSER_EL = document.getElementById('disc-composer');
    const SHEET = document.getElementById('disc-sheet');
    const SHEET_BACKDROP = document.getElementById('disc-sheet-backdrop');
    const SHEET_TITLE = document.getElementById('disc-sheet-title');
    const SHEET_DELETE_ME = document.getElementById('disc-sheet-delete-me');
    const SHEET_DELETE_ALL = document.getElementById('disc-sheet-delete-all');
    const SHEET_CANCEL = document.getElementById('disc-sheet-cancel');
    const STATE_URL = '{{ route("discussion.fetch") }}';
    const SEND_URL = '{{ route("discussion.send") }}';
    const TYPING_URL = '{{ route("discussion.typing") }}';
    const GIFS_URL = '{{ route("discussion.gifs") }}';
    const FAVORITES_URL = '{{ route("discussion.favorites") }}';
    const FAVORITES_TOGGLE_URL = '{{ route("discussion.favorites.toggle") }}';
    const DELETE_URL = '/discussion/message/';
    const MY_ID = {{ $me->id }};
    const PARTNER_NAME = @json($partenaire->name);

    // Ensemble des id déjà rendus pour éviter tout doublon.
    const renderedIds = new Set();
    let lastMessageId = 0;
    let lastDate = '';
    let sending = false;
    let replyTarget = null; // {id, sender_name, body}
    let pendingGif = null; // {url, alt} sélectionné dans le panneau GIF
    const selectedMsgs = new Map(); // id -> {msg, wrap}
    let lastToggleAt = -9999; // dernier moment où la sélection a été basculée (anti-rebond/doublon)

    function scrollToBottom(smooth) {
        if (smooth) {
            MESSAGES_EL.scrollTo({ top: MESSAGES_EL.scrollHeight, behavior: 'smooth' });
        } else {
            MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        }
    }

    // Ajuste la hauteur du textarea à son contenu, comme WhatsApp.
    function autosize() {
        INPUT_EL.style.height = 'auto';
        INPUT_EL.style.height = INPUT_EL.scrollHeight + 'px';
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

    /* ---------- Sélection multi-messages (style WhatsApp) ---------- */

    function enterSelection() {
        MESSAGES_EL.classList.add('disc-selecting');
        DISC_HEADER.style.display = 'none';
        SEL_BAR.style.display = 'flex';
        REPLYBAR_EL.style.display = 'none';
        updateSelectionBar();
    }

    function exitSelection() {
        MESSAGES_EL.classList.remove('disc-selecting');
        selectedMsgs.forEach(item => item.wrap.classList.remove('disc-checked'));
        selectedMsgs.clear();
        DISC_HEADER.style.display = '';
        SEL_BAR.style.display = 'none';
        if (replyTarget) setReply(null);
    }

    function toggleSelect(msg, wrap) {
        lastToggleAt = Date.now();
        if (selectedMsgs.has(msg.id)) {
            selectedMsgs.delete(msg.id);
            wrap.classList.remove('disc-checked');
        } else {
            selectedMsgs.set(msg.id, { msg, wrap });
            wrap.classList.add('disc-checked');
        }
        if (selectedMsgs.size === 0) {
            exitSelection();
            return;
        }
        if (DISC_HEADER.style.display !== 'none') enterSelection();
        updateSelectionBar();
    }

    function updateSelectionBar() {
        const n = selectedMsgs.size;
        SEL_COUNT.textContent = String(n);
        // Répondre : uniquement si un seul message est sélectionné.
        SEL_REPLY.style.display = n === 1 ? '' : 'none';
        // Supprimer pour tous : seulement si TOUS les messages sélectionnés sont à moi.
        const allMine = [...selectedMsgs.values()].every(item => String(item.msg.sender_id) === String(MY_ID));
        SEL_DELETE.dataset.canAll = allMine ? '1' : '0';
    }

    SEL_BACK.addEventListener('click', exitSelection);
    SEL_REPLY.addEventListener('click', () => {
        if (selectedMsgs.size !== 1) return;
        const { msg } = selectedMsgs.values().next().value;
        const replyMsg = msg;
        exitSelection();      // retire d'abord le mode sélection (annule replyTarget)
        setReply(replyMsg);   // puis affiche la barre de réponse
        INPUT_EL.focus();
    });
    SEL_DELETE.addEventListener('click', () => {
        const allMine = SEL_DELETE.dataset.canAll === '1';
        SHEET_DELETE_ALL.style.display = allMine ? '' : 'none';
        SHEET_TITLE.textContent = selectedMsgs.size > 1
            ? 'Supprimer ' + selectedMsgs.size + ' messages'
            : 'Supprimer ce message';
        openSheet();
    });

    // ---- Bottom-sheet suppression ----
    function openSheet() {
        SHEET_BACKDROP.style.display = 'block';
        SHEET.style.display = 'flex';
    }
    function closeSheet() {
        SHEET_BACKDROP.style.display = 'none';
        SHEET.style.display = 'none';
    }
    SHEET_CANCEL.addEventListener('click', closeSheet);
    SHEET_BACKDROP.addEventListener('click', closeSheet);

    SHEET_DELETE_ME.addEventListener('click', () => {
        closeSheet();
        deleteMessages(Array.from(selectedMsgs.keys()), 'me');
    });
    SHEET_DELETE_ALL.addEventListener('click', () => {
        closeSheet();
        deleteMessages(Array.from(selectedMsgs.keys()), 'all');
    });

    async function deleteMessages(ids, mode) {
        let failed = false;
        for (const id of ids) {
            try {
                const res = await fetch(DELETE_URL + id, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ mode }),
                });
                if (res.ok) {
                    const wrap = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + id + '"]');
                    if (mode === 'all') {
                        if (wrap) {
                            const bubble = wrap.querySelector('.disc-bubble');
                            if (bubble) {
                                bubble.innerHTML = '';
                                bubble.classList.add('disc-bubble-deleted');
                                const txt = document.createElement('span');
                                txt.className = 'disc-deleted-text';
                                txt.textContent = 'Vous avez supprimé ce message';
                                bubble.appendChild(txt);
                            }
                            wrap.replaceWith(wrap.cloneNode(true));
                        }
                        selectedMsgs.delete(id);
                    } else {
                        if (wrap) wrap.remove();
                        selectedMsgs.delete(id);
                        renderedIds.delete(id);
                    }
                } else {
                    const err = await res.json().catch(() => ({}));
                    toast(err.error || 'Erreur lors de la suppression.', 'error');
                    failed = true;
                }
            } catch (e) {
                toast('Connexion perdue.', 'error');
                failed = true;
            }
        }
        if (selectedMsgs.size === 0) exitSelection();
        if (failed) return;
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

        // Message supprimé pour tous : bulle grisée avec texte placeholder.
        if (msg.deleted_for_all) {
            bubble.classList.add('disc-bubble-deleted');
            const txt = document.createElement('span');
            txt.className = 'disc-deleted-text';
            txt.textContent = msg.deleted_by_me
                ? 'Vous avez supprimé ce message'
                : 'Ce message a été supprimé';
            bubble.appendChild(txt);
            wrap.appendChild(bubble);
            MESSAGES_EL.appendChild(wrap);
            return;
        }

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

        // Message GIF/sticker : grande image au lieu du texte.
        if (msg.is_gif && msg.gif_url) {
            const imgWrap = document.createElement('div');
            imgWrap.className = 'disc-gif';
            const img = document.createElement('img');
            img.src = msg.gif_url;
            img.alt = msg.gif_alt || 'GIF';
            img.loading = 'lazy';
            imgWrap.appendChild(img);
            bubble.appendChild(imgWrap);
        }

        if (msg.body) {
            const bodyText = document.createElement('span');
            bodyText.className = 'disc-bubble-text';
            bodyText.textContent = msg.body;
            bubble.appendChild(bodyText);
        }

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

        // Clic droit (desktop) → sélectionner le message.
        // NB : sur Android, l'appui long déclenche AUSSI un `contextmenu` natif,
        // juste après le long press (timer touche). Sans garde, les deux appelleraient
        // toggleSelect → sélection puis désélection immédiate → la barre clignote et
        // disparaît. On ignore donc le contextmenu s'il survient juste après un toggle.
        wrap.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            if (Date.now() - lastToggleAt < 300) return; // doublon du long press Android
            toggleSelect(msg, wrap);
        });

        // Interactions tactiles : appui long = sélection, swipe droite = répondre.
        (function attachTouch(wrap, msg) {
            let startX = null;
            let startY = null;
            let touchTimer = null;
            let longPressFired = false;

            wrap.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                longPressFired = false;
                clearTimeout(touchTimer);
                touchTimer = setTimeout(() => {
                    longPressFired = true;
                    if (!msg.deleted_for_all) toggleSelect(msg, wrap);
                }, 500);
            }, { passive: true });

            // En mode sélection, un simple appui bascule la sélection ; sinon,
            // un swipe net vers la droite répond au message.
            wrap.addEventListener('touchend', (e) => {
                clearTimeout(touchTimer);
                if (startX === null) return;
                const touch = e.changedTouches[0];
                const dx = touch.clientX - startX;
                const dy = touch.clientY - startY;

                // Verrouillage anti-rebond : un changement de layout (entrée en
                // mode sélection) peut générer des touchestart/touchend parasites
                // juste après un long press, qui désélectionneraient le message et
                // feraient clignoter la barre. On ignore donc les taps pendant un
                // court instant après chaque bascule.
                if (Date.now() - lastToggleAt < 300) { startX = null; return; }

                if (selectedMsgs.size > 0) {
                    if (!longPressFired && Math.abs(dx) < 10 && Math.abs(dy) < 10 && !msg.deleted_for_all) {
                        toggleSelect(msg, wrap);
                    }
                } else if (!longPressFired && dx > 60 && Math.abs(dy) < 40) {
                    setReply(msg);
                }
                startX = null;
            }, { passive: true });

            wrap.addEventListener('touchcancel', () => { clearTimeout(touchTimer); startX = null; });
        })(wrap, msg);
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
        if ((!body && !pendingGif) || sending) return;

        sending = true;
        SEND_BTN.disabled = true;

        const payload = { body, reply_to_id: replyTarget ? replyTarget.id : null };
        if (pendingGif) {
            payload.gif_url = pendingGif.url;
            payload.gif_alt = pendingGif.alt;
        }

        try {
            const res = await fetch(SEND_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) {
                const data = await res.json();
                const replyingId = replyTarget ? replyTarget.id : null;
                const wasDown = wasAtBottom();
                buildBubble({
                    id: data.id,
                    sender_id: MY_ID,
                    body: body,
                    is_gif: !!pendingGif,
                    gif_url: pendingGif ? pendingGif.url : null,
                    gif_alt: pendingGif ? pendingGif.alt : null,
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
                pendingGif = null;
                closeGifPanel();
                INPUT_EL.value = '';
                autosize();
                SEND_BTN.disabled = true;
            } else {
                toast(pendingGif ? 'Erreur lors de l\'envoi du GIF.' : 'Erreur lors de l\'envoi.', 'error');
            }
        } catch (e) {
            toast('Connexion perdue.', 'error');
        } finally {
            sending = false;
            SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif;
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

    /* ---------- Panneau GIF + favoris ---------- */
    let gifRequest = null;
    let activeTab = 'search';
    let favCache = [];      // {id, url, alt} des favoris
    let favUrlSet = new Set();

    function closeGifPanel() {
        GIF_PANEL.style.display = 'none';
        GIF_SEARCH.value = '';
        GIF_GRID.innerHTML = '';
        GIF_BTN.classList.remove('active');
        SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif;
    }

    function openGifPanel() {
        GIF_PANEL.style.display = 'flex';
        GIF_BTN.classList.add('active');
        showTab('search');
        GIF_SEARCH.focus();
    }

    function showTab(tab) {
        activeTab = tab;
        TAB_SEARCH.classList.toggle('active', tab === 'search');
        TAB_FAVS.classList.toggle('active', tab === 'favs');
        if (tab === 'favs') {
            loadFavorites();
        } else {
            loadGifs(GIF_SEARCH.value.trim());
        }
    }

    async function loadFavorites() {
        GIF_GRID.innerHTML = '<div class="disc-gif-loading">Chargement…</div>';
        try {
            const res = await fetch(FAVORITES_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                GIF_GRID.innerHTML = '<div class="disc-gif-error">Impossible de charger les favoris.</div>';
                return;
            }
            const data = await res.json();
            favCache = data.favorites || [];
            favUrlSet = new Set(favCache.map(f => f.url));
            renderGifGrid(favCache.map(f => ({ url: f.url, alt: f.alt, isFav: true })));
        } catch (e) {
            GIF_GRID.innerHTML = '<div class="disc-gif-error">Connexion perdue.</div>';
        }
    }

    async function loadGifs(query) {
        if (gifRequest) gifRequest.abort();
        GIF_GRID.innerHTML = '<div class="disc-gif-loading">Chargement…</div>';
        gifRequest = new AbortController();
        try {
            const res = await fetch(GIFS_URL + '?q=' + encodeURIComponent(query), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: gifRequest.signal,
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                GIF_GRID.innerHTML = '<div class="disc-gif-error">' + escHtml(err.error || 'Impossible de charger les GIF.') + '</div>';
                return;
            }
            const data = await res.json();
            const items = (data.gifs || []).map(g => ({
                url: g.url,
                alt: g.alt || '',
                preview: g.preview,
                isFav: favUrlSet.has(g.url),
            }));
            renderGifGrid(items);
        } catch (e) {
            if (e.name !== 'AbortError') {
                GIF_GRID.innerHTML = '<div class="disc-gif-error">Connexion perdue.</div>';
            }
        }
    }

    async function toggleFavorite(url, alt) {
        const res = await fetch(FAVORITES_TOGGLE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ gif_url: url, gif_alt: alt || '' }),
        });
        if (res.ok) {
            const data = await res.json();
            favCache = data.favorites || [];
            favUrlSet = new Set(favCache.map(f => f.url));
            // Rafraîchit la grille selon l'onglet actif.
            if (activeTab === 'favs') {
                renderGifGrid(favCache.map(f => ({ url: f.url, alt: f.alt, isFav: true })));
            } else {
                updateFavStars();
            }
        }
    }

    function updateFavStars() {
        GIF_GRID.querySelectorAll('.disc-gif-item').forEach(item => {
            const url = item.dataset.url;
            const star = item.querySelector('.disc-gif-star');
            if (star) star.classList.toggle('active', favUrlSet.has(url));
        });
    }

    function renderGifGrid(gifs) {
        GIF_GRID.innerHTML = '';
        if (gifs.length === 0) {
            GIF_GRID.innerHTML = '<div class="disc-gif-error">' + (activeTab === 'favs' ? 'Aucun favori pour l\'instant.' : 'Aucun GIF trouvé.') + '</div>';
            return;
        }
        for (const g of gifs) {
            const wrap = document.createElement('div');
            wrap.className = 'disc-gif-item';
            wrap.dataset.url = g.url;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'disc-gif-select';
            const img = document.createElement('img');
            img.src = g.url || g.preview;
            img.alt = g.alt || 'GIF';
            img.loading = 'lazy';
            btn.appendChild(img);

            const star = document.createElement('button');
            star.type = 'button';
            star.className = 'disc-gif-star' + (g.isFav ? ' active' : '');
            star.setAttribute('aria-label', 'Favori');
            star.textContent = '★';

            btn.addEventListener('click', () => {
                pendingGif = { url: g.url, alt: g.alt || 'GIF' };
                sendMessage();
            });
            star.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFavorite(g.url, g.alt);
            });

            wrap.appendChild(btn);
            wrap.appendChild(star);
            GIF_GRID.appendChild(wrap);
        }
    }

    TAB_SEARCH.addEventListener('click', () => showTab('search'));
    TAB_FAVS.addEventListener('click', () => showTab('favs'));

    GIF_BTN.addEventListener('click', () => {
        if (GIF_PANEL.style.display === 'flex') closeGifPanel();
        else openGifPanel();
    });
    GIF_CLOSE.addEventListener('click', closeGifPanel);
    GIF_SEARCH.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadGifs(GIF_SEARCH.value.trim());
        }
    });
    GIF_SEARCH.addEventListener('input', () => {
        clearTimeout(GIF_SEARCH._t);
        GIF_SEARCH._t = setTimeout(() => {
            const v = GIF_SEARCH.value.trim();
            if (v.length >= 2 || v.length === 0) loadGifs(v);
        }, 400);
    });

    INPUT_EL.addEventListener('input', () => {
        autosize();
        SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif;
        if (INPUT_EL.value.trim()) sendTyping();
    });
    INPUT_EL.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;

        // Sur mobile, la touche Entrée (retour à la ligne) insère une nouvelle
        // ligne comme sur WhatsApp : l'envoi passe par le bouton d'envoi.
        const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        if (isTouch && !e.shiftKey) return; // laisser le textarea insérer le \n

        // Sur desktop : Entrée envoie, Maj+Entrée nouvelle ligne.
        if (!e.shiftKey) {
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
