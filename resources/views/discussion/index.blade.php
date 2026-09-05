@extends('layouts.app')

@section('title', 'Discussion')

@push('head')
<style>
    /* Pas de barre de navigation en bas sur la page discussion :
       le chat occupe tout l'espace jusqu'en bas de l'écran. */
    body .bottom-nav { display: none !important; }
    /* Le chat s'étire de la topbar jusqu'EN BAS DE L'ÉCRAN (clavier fermé) :
       le composer reste collé tout en bas, sans espace. Quand le clavier
       s'ouvre, le JS remonte "bottom" au-dessus du clavier uniquement si le
       navigateur ne redimensionne pas le contenu (iOS). */
    .disc-wrap {
        bottom: 0;
        top: 64px;
        height: auto;
    }
    /* --- iOS : le document ne défile jamais (seule la liste de messages défile).
       C'est ce qui empêchait iOS de "pan" le chat quand le clavier est ouvert
       et créait l'espace entre le composer et le clavier. --- */
    html, body { overflow: hidden; }
    .disc-messages { overscroll-behavior: contain; } /* le scroll s'arrête dans la liste */
    /* La topbar et le header ne doivent pas réagir au rubber-band iOS */
    body .topbar, .disc-header { touch-action: none; }
    /* Le composer est collé en bas de l'écran : pas de safe-area (la zone du
       home indicator reste tapée sur le fond sombre), pour qu'il soit collé
       sur iOS exactement comme sur Android. */
    .disc-composer {
        padding-bottom: 10px;
    }
    body.disc-edit .disc-composer { padding-bottom: 3px; }
</style>
<script>
    // Clavier mobile : hauteur du chat = hauteur visible (Visual Viewport) − topbar.
    // Le composer, dernier élément en flux du wrap, reste ainsi scotché au-dessus
    // du clavier, et l'en-tête (profil) juste sous la topbar.
    (function () {
        var wrap = null;
        var TOP_H = 64;
        var pressing = false;
        function layout() {
            if (!wrap) wrap = document.getElementById('disc-wrap');
            if (!wrap) return;
            // Pendant une pression (pouce posé sur un bouton), on ne touche pas
            // au layout : sinon le composant bouge sous le doigt quand le clavier
            // se ferme (blur) et le clic est impossible.
            if (pressing) return;
            var vv = window.visualViewport;
            var vh = window.innerHeight || document.documentElement.clientHeight;
            var focus = document.activeElement;
            var editing = focus && (focus.tagName === 'INPUT' || focus.tagName === 'TEXTAREA');
            // Sur iOS, la vue visible est plus courte que le document même clavier
            // fermé (barres d'URL/outils en surimpression). On ne remonte le bas du
            // chat que si le CLAVIER est vraiment ouvert (champ focus + vue réduite).
            // Clavier fermé sur iOS ou Android : bottom 0 → barre collée en bas.
            var kbShifts = !!(vv && vv.height + 50 < vh);
            var kbOpen = editing && kbShifts;
            // "disc-edit" reste actif tant que le clavier est là (même après un blur) :
            // le composer garde son padding réduit, aucun saut au moment d'un tap.
            if (document.body) document.body.classList.toggle('disc-edit', editing || kbShifts);
            // État du scroller AVANT le redimensionnement : si l'utilisateur était
            // en bas, on y reste (le dernier message reste visible au-dessus du
            // clavier), sans jamais "défiler" pour y retourner.
            var scroller = document.getElementById('disc-messages');
            var wasDown = !!scroller && (scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 80);
            // Mesure de la topbar seulement clavier fermé (layout stable).
            // La barre ne doit jamais remonter au-dessus de la position CSS
            // (top: 64px) : on plafonne la mesure en dessous de 64.
            if (!editing && !kbShifts) {
                var tb = document.querySelector('.topbar');
                if (tb) {
                    var n = Math.round(tb.getBoundingClientRect().bottom);
                    if (n > 0 && n < 400) TOP_H = Math.max(64, n);
                }
            }
            // Top : juste sous la topbar (jamais au-dessus de la position CSS).
            wrap.style.top = TOP_H + 'px';
            // Bas : au-dessus du clavier si celui-ci recouvre (iOS, clavier ouvert),
            // sinon collé au bas de l'écran, sur iOS comme sur Android.
            if (kbOpen) {
                wrap.style.bottom = Math.max(0, Math.round(vh - (vv.offsetTop || 0) - vv.height)) + 'px';
            } else {
                wrap.style.bottom = '0px';
            }
            wrap.style.height = 'auto';
            // Le clavier rétrécit la zone visible : si on était collé en bas, on
            // recale sans mouvement (dernier message juste au-dessus du clavier).
            if (kbOpen && wasDown && scroller) {
                scroller.scrollTop = scroller.scrollHeight - scroller.clientHeight;
            }
        }
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', layout);
            window.visualViewport.addEventListener('scroll', layout);
        }
        window.addEventListener('resize', layout);
        document.addEventListener('DOMContentLoaded', layout);
        window.setTimeout(layout, 300);
        window.addEventListener('load', layout);

        // Gel du layout pendant toute la durée d'une pression au doigt/pointeur.
        document.addEventListener('pointerdown', function () { pressing = true; }, true);
        document.addEventListener('pointerup', function () {
            pressing = false;
            layout(); // on recale dès que le doigt est levé
        }, true);
        document.addEventListener('pointercancel', function () {
            pressing = false;
            layout();
        }, true);

        // Filet de sécurité : si iOS décale le document pendant le clavier ouvert
        // (auto-scroll au focus), on l'annule immédiatement.
        window.addEventListener('scroll', function () {
            var a = document.activeElement;
            var editing = a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA');
            if (editing && (window.scrollY || 0) > 0) window.scrollTo(0, 0);
        }, { passive: true });

        // Pendant la saisie, on remesure régulièrement : certains iOS n'émettent
        // pas le dernier événement visualViewport à la fin de l'animation du clavier.
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('disc-input');
            if (!input) return;
            var iv = null;
            input.addEventListener('focus', function () {
                if (iv) clearInterval(iv);
                iv = setInterval(layout, 200);
            });
            input.addEventListener('blur', function () {
                if (iv) { clearInterval(iv); iv = null; }
                layout();
            });
            // Tap sur un bouton du composer (envoi, gif, photo, micro…) : empêcher
            // qu'il vole le focus de l'input. Sinon le clavier se ferme, la barre
            // saute en bas (« les boutons fuient ») et le clic est perdu.
            document.querySelectorAll('.disc-composer button').forEach(function (btn) {
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            });
        });
    })();
</script>
@endpush
@php
    $me = auth()->user();
    $partenaire = $couple->partnerOf($me);
    $pEnLigne = $partenaire?->last_active_at && $partenaire->last_active_at->diffInMinutes() < 1;
    $pPresent = (bool) $partenaire?->last_active_at;
@endphp

@section('content')
    <div class="disc-wrap" id="disc-wrap">
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
                        <span class="muted">en ligne il y'a {{ $partenaire->last_active_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
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

        {{-- Messages (occupent l'espace libre entre header et composer).
             Rendu côté client : les données injectées dans #disc-init-messages
             sont construites avec le MÊME buildBubble que d'habitude, AVANT la
             première peinture → ouverture directe sur le dernier message, sans
             défilement ni flash, avec un affichage strictement identique. --}}
        <div class="disc-messages" id="disc-messages" style="visibility:hidden">
            <div class="disc-welcome">
                <div style="font-size:36px; margin-bottom:8px">💬</div>
                <div class="tiny muted">Vos messages sont privés.<br>Discutez de tout, à tout moment.</div>
            </div>
        </div>

        {{-- Données injectées par le serveur (JSON brut, aucun HTML de bulle).
             JSON_HEX_TAG/APOS/QUOT protège le contenu contre toute fermeture
             anticipée du bloc, même avec du texte/vocal/photo utilisateur. --}}
        <script type="application/json" id="disc-init-messages">{!! json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

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
            <button class="disc-camera-btn" id="disc-camera-btn" type="button" aria-label="Envoyer une photo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                </svg>
            </button>
            <input type="file" id="disc-photo-input" accept="image/*" style="display:none">
            {{-- Aperçu de la photo à envoyer, au-dessus du composer --}}
            <div class="disc-photo-preview" id="disc-photo-preview" style="display:none">
                <img id="disc-photo-preview-img" alt="Aperçu de la photo">
                <button class="disc-photo-preview-close" id="disc-photo-preview-close" aria-label="Retirer la photo">✕</button>
            </div>
            <button class="disc-mic-btn" id="disc-mic-btn" type="button" aria-label="Enregistrer un message vocal">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="2" width="6" height="12" rx="3"></rect>
                    <path d="M5 10a7 7 0 0 0 14 0"></path>
                    <line x1="12" y1="19" x2="12" y2="22"></line>
                </svg>
            </button>
            {{-- Barre d'enregistrement vocal (façon WhatsApp) : annuler à gauche,
                 durée + bande son au milieu, envoyer à droite --}}
            <div class="disc-rec-bar" id="disc-rec-bar" style="display:none">
                <button type="button" class="disc-rec-cancel" id="disc-rec-cancel" aria-label="Supprimer le vocal">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="disc-rec-center">
                    <span class="disc-rec-time" id="disc-rec-time">0:00</span>
                    <div class="disc-rec-waves" id="disc-rec-waves"></div>
                </div>
                <button type="button" class="disc-rec-send" id="disc-rec-send" aria-label="Envoyer le vocal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
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
            <button type="button" class="disc-gif-tab" data-tab="stickers" id="disc-tab-stickers">Stickers 😍</button>
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

    {{-- Visionneuse plein écran d'une photo : zoom + téléchargement (style WhatsApp) --}}
    <div class="disc-photo-viewer" id="disc-photo-viewer" style="display:none">
        <button class="disc-photo-viewer-close" id="disc-photo-viewer-close" aria-label="Fermer">✕</button>
        <button class="disc-photo-viewer-nav disc-photo-viewer-prev" id="disc-photo-viewer-prev" aria-label="Photo précédente" hidden>‹</button>
        <button class="disc-photo-viewer-nav disc-photo-viewer-next" id="disc-photo-viewer-next" aria-label="Photo suivante" hidden>›</button>
        <img id="disc-photo-viewer-img" alt="Photo agrandie">
        <div class="disc-photo-viewer-counter" id="disc-photo-viewer-counter"></div>
        <div class="disc-photo-viewer-actions">
            <a class="disc-photo-viewer-download" id="disc-photo-viewer-download" download>⬇ Télécharger</a>
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
    const GIF_BTN = document.getElementById('disc-gif-btn');
    const GIF_PANEL = document.getElementById('disc-gifpanel');
    const GIF_SEARCH = document.getElementById('disc-gif-search');
    const GIF_GRID = document.getElementById('disc-gif-grid');
    const GIF_CLOSE = document.getElementById('disc-gif-close');
    const TAB_SEARCH = document.getElementById('disc-tab-search');
    const TAB_FAVS = document.getElementById('disc-tab-favs');
    const TAB_STICKERS = document.getElementById('disc-tab-stickers');
    const CAMERA_BTN = document.getElementById('disc-camera-btn');
    const PHOTO_INPUT = document.getElementById('disc-photo-input');
    const PHOTO_PREVIEW = document.getElementById('disc-photo-preview');
    const PHOTO_PREVIEW_IMG = document.getElementById('disc-photo-preview-img');
    const PHOTO_PREVIEW_CLOSE = document.getElementById('disc-photo-preview-close');
    const PHOTO_VIEWER = document.getElementById('disc-photo-viewer');
    const PHOTO_VIEWER_IMG = document.getElementById('disc-photo-viewer-img');
    const PHOTO_VIEWER_CLOSE = document.getElementById('disc-photo-viewer-close');
    const PHOTO_VIEWER_DOWNLOAD = document.getElementById('disc-photo-viewer-download');
    const PHOTO_VIEWER_PREV = document.getElementById('disc-photo-viewer-prev');
    const PHOTO_VIEWER_NEXT = document.getElementById('disc-photo-viewer-next');
    const PHOTO_VIEWER_COUNTER = document.getElementById('disc-photo-viewer-counter');
    const MIC_BTN = document.getElementById('disc-mic-btn');
    const REC_BAR = document.getElementById('disc-rec-bar');
    const REC_TIME = document.getElementById('disc-rec-time');
    const REC_WAVES = document.getElementById('disc-rec-waves');
    const REC_SEND = document.getElementById('disc-rec-send');
    const REC_CANCEL = document.getElementById('disc-rec-cancel');
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
    const PHOTO_URL = '{{ route("discussion.photo") }}';
    const AUDIO_URL = '{{ route("discussion.audio") }}';
    const TYPING_URL = '{{ route("discussion.typing") }}';
    const RECORDING_URL = '{{ route("discussion.recording") }}';
    const GIFS_URL = '{{ route("discussion.gifs") }}';
    const STICKERS_URL = '{{ route("discussion.stickers") }}';
    const FAVORITES_URL = '{{ route("discussion.favorites") }}';
    const FAVORITES_TOGGLE_URL = '{{ route("discussion.favorites.toggle") }}';
    const DELETE_URL = '/discussion/message/';
    const MY_ID = {{ $me->id }};
    const PARTNER_NAME = @json($partenaire->name);
    const ICON_PLAY = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
    const ICON_PAUSE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';
    const ICON_MIC = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M18 11a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V21h2v-2.07A8 8 0 0 0 20 11h-2z"/></svg>';

    // Ensemble des id déjà rendus pour éviter tout doublon.
    const renderedIds = new Set();
    let lastMessageId = 0;
    let lastDate = '';
    let sending = false;
    let replyTarget = null; // {id, sender_name, body}
    let pendingGif = null; // {url, alt} sélectionné dans le panneau GIF
    let pendingPhoto = null; // {path, url} photo choisie à envoyer
    let pendingAudio = null; // {path, url, duration, bars} vocal enregistré à envoyer
    let activeAudio = null; // <audio> en cours de lecture (un seul à la fois)
    let micRecorder = null;
    let micStream = null;
    let micChunks = [];
    let micStartedAt = 0;
    let micTimer = null;
    let micCtx = null;      // AudioContext pour la bande son en direct
    let micAnalyser = null;
    let micFreq = null;
    let waveBars = [];      // <span> de la bande son d'enregistrement
    let waveAnim = null;    // requestAnimationFrame de la bande son en direct
    const selectedMsgs = new Map(); // id -> {msg, wrap}
    let lastToggleAt = -9999; // dernier moment où la sélection a été basculée (anti-rebond/doublon)
    let initialLoad = true; // premier chargement : scroll direct en bas

    // Colle en bas, toujours sans animation (aucun défilement visible) :
    // à l'envoi comme à la réception, le dernier message apparaît d'un coup.
    function scrollToBottom() {
        MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
    }

    // Au premier chargement : colle tout en bas puis re-colle quand les images
    // oneshot (lazy) finissent de charger, sinon leur arrivée remonte le contenu
    // et laisse un espace en bas.
    function stickyToBottomOnce() {
        initialLoad = false;
        const imgs = Array.from(MESSAGES_EL.querySelectorAll('img'));
        let remaining = imgs.filter((i) => !i.complete).length;
        if (remaining === 0) {
            MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
            return;
        }
        const done = () => {
            remaining--;
            if (remaining === 0) MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        };
        for (const img of imgs) {
            if (img.complete) continue;
            img.addEventListener('load', done);
            img.addEventListener('error', done);
        }
        // Filet de sécurité : re-colle en bas de toute façon après 3 s.
        setTimeout(() => { MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight; }, 3000);
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

    // Câble la lecture du vocal (play/pause, progression, durée) sur un bloc
    // `.disc-audio` déjà présent dans le DOM (bulle créée en JS ou pré-rendue).
    function wireAudio(audioWrap, msg) {
        const playBtn = audioWrap.querySelector('.disc-audio-play');
        const prog = audioWrap.querySelector('.disc-vw-prog');
        const time = audioWrap.querySelector('.disc-audio-time');
        const audio = audioWrap.querySelector('audio');
        if (!playBtn || !prog || !time || !audio) return;

        const totalMs = msg.audio_duration || 0;
        let realDur = 0;
        realAudioDuration(msg.audio_url).then((d) => {
            if (d > 0) {
                realDur = d;
                time.textContent = formatAudioTime(d);
            }
        });
        const effDuration = () => realDur > 0 ? realDur
            : ((isFinite(audio.duration) && audio.duration > 0) ? audio.duration : totalMs);

        const setIcon = (playing) => {
            playBtn.classList.toggle('playing', playing);
            playBtn.innerHTML = playing ? ICON_PAUSE : ICON_PLAY;
        };
        const setProgress = (pct) => { prog.style.width = Math.min(100, Math.max(0, pct)) + '%'; };
        const showElapsed = () => {
            const t = audio.currentTime;
            time.textContent = formatAudioTime((isFinite(t) && t >= 0) ? t : (realDur || totalMs));
        };

        audio.addEventListener('play', () => { setIcon(true); setProgress(0); });
        audio.addEventListener('timeupdate', () => {
            const t = audio.currentTime;
            if (isFinite(t) && effDuration() > 0) {
                setProgress((t / effDuration()) * 100);
                if (!audio.paused) showElapsed();
            }
        });
        audio.addEventListener('pause', () => { setIcon(false); showElapsed(); });
        audio.addEventListener('ended', () => {
            setIcon(false);
            setProgress(100);
            time.textContent = formatAudioTime(realDur || totalMs);
            activeAudio = null;
        });
        playBtn.addEventListener('click', () => {
            if (activeAudio && activeAudio !== audio && !activeAudio.paused) activeAudio.pause();
            if (audio.paused) {
                audio.play().catch(() => toast('Lecture impossible.', 'error'));
                activeAudio = audio;
            } else {
                audio.pause();
                activeAudio = null;
            }
        });
    }

    // Câble les interactions d'une bulle (clic droit/long press = sélection,
    // swipe droite = répondre) sur un bloc `.disc-bubble-wrap` (JS ou pré-rendu).
    function wireMessage(wrap, msg) {
        wrap.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            if (Date.now() - lastToggleAt < 300) return; // doublon du long press Android
            toggleSelect(msg, wrap);
        });

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
            wrap.addEventListener('touchend', (e) => {
                clearTimeout(touchTimer);
                if (startX === null) return;
                const touch = e.changedTouches[0];
                const dx = touch.clientX - startX;
                const dy = touch.clientY - startY;
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

        // Message photo : image hébergée localement. Si la taille native est
        // connue, on réserve la hauteur (aspect-ratio) dès la construction :
        // le chargement ne peut plus décaler le fil (plus de défilement à
        // l'entrée, même sur mobile).
        if (msg.is_photo && msg.photo_url) {
            const imgWrap = document.createElement('div');
            imgWrap.className = 'disc-photo';
            const img = document.createElement('img');
            img.src = msg.photo_url;
            img.alt = 'Photo';
            if (msg.photo_w && msg.photo_h) {
                img.style.aspectRatio = msg.photo_w + ' / ' + msg.photo_h;
            }
            img.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openPhotoViewer(img.src, img.alt);
            });
            imgWrap.appendChild(img);
            bubble.appendChild(imgWrap);
        }

        // Message vocal (façon WhatsApp) : avatar du partenaire DANS la bulle (avec
// badge micro en bas à droite), bouton lecture à côté, bande son + durée.
        if (msg.is_audio && msg.audio_url) {
            const bars = parseBars(msg.audio_bars, 36) || barsFromId(msg.id, 36);

            const audioWrap = document.createElement('div');
            audioWrap.className = 'disc-audio';
            audioWrap.prepend(makeAvatar(msg));
            const playBtn = document.createElement('button');
            playBtn.type = 'button';
            playBtn.className = 'disc-audio-play';
            playBtn.setAttribute('aria-label', 'Écouter le vocal');
            playBtn.innerHTML = ICON_PLAY;

            const body = document.createElement('div');
            body.className = 'disc-audio-body';

            // Bande son : couche de base + couche de progression (coupe gauche→droite).
            const vw = document.createElement('div');
            vw.className = 'disc-vw';
            const base = document.createElement('div');
            base.className = 'disc-vw-layer';
            const prog = document.createElement('div');
            prog.className = 'disc-vw-prog';
            prog.style.width = '0%';
            bars.forEach((h) => {
                const bar = document.createElement('span');
                bar.className = 'disc-vw-bar';
                bar.style.height = (4 + Math.round(h / 100 * 28)) + 'px';
                base.appendChild(bar);
                prog.appendChild(bar.cloneNode(false));
            });
            vw.appendChild(base);
            vw.appendChild(prog);

            const time = document.createElement('span');
            time.className = 'disc-audio-time';
            time.textContent = formatAudioTime(msg.audio_duration || 0);

            const audio = document.createElement('audio');
            audio.src = msg.audio_url;
            audio.preload = 'none';

            body.appendChild(vw);
            body.appendChild(time);
            audioWrap.appendChild(playBtn);
            audioWrap.appendChild(body);
            audioWrap.appendChild(audio);
            bubble.appendChild(audioWrap);
            // Câble la lecture (partagé avec les bulles pré-rendues côté serveur).
            wireAudio(audioWrap, msg);
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

        // Sélection (clic droit/long press) + répondre (swipe droite) : partagé
        // avec les bulles pré-rendues côté serveur.
        wireMessage(wrap, msg);
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
                // Au tout premier chargement on colle directement tout en bas (scroll
                // instantané), sinon on reste en bas de façon animée à chaque poll.
                if (initialLoad) {
                    scrollToBottom();
                    stickyToBottomOnce();
                } else if (bottom || hasIncoming) {
                    scrollToBottom();
                }
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

    let pushCleared = false; // évite de re-fermer les notifications à chaque poll

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
            pushCleared = false; // de nouveaux non-lus arrivent → on retirera à la prochaine lecture
        } else {
            if (badge) badge.remove();
            // Plus aucun message non lu (l'utilisateur vient de tout lire) : on
            // ferme les notifications de la barre du téléphone et on réinitialise
            // le badge de l'icône de l'app installée.
            if (!pushCleared) {
                pushCleared = true;
                clearPushNotifications();
            }
        }
    }

    // Demande au Service Worker de fermer les notifications du téléphone et de
    // remettre le badge de l'icône à zéro (l'utilisateur vient de tout lire).
    function clearPushNotifications() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_NOTIFICATIONS' });
        }
    }

    async function sendMessage() {
        const body = INPUT_EL.value.trim();
        if ((!body && !pendingGif && !pendingPhoto && !pendingAudio) || sending) return;

        sending = true;
        SEND_BTN.disabled = true;

        const payload = { body, reply_to_id: replyTarget ? replyTarget.id : null };
        const hasGif = !!pendingGif;
        if (pendingGif) {
            payload.gif_url = pendingGif.url;
            payload.gif_alt = pendingGif.alt;
        }
        if (pendingPhoto) {
            payload.photo_path = pendingPhoto.path;
            if (pendingPhoto.w && pendingPhoto.h) {
                payload.photo_w = pendingPhoto.w;
                payload.photo_h = pendingPhoto.h;
            }
        }
        if (pendingAudio) {
            payload.audio_path = pendingAudio.path;
            payload.audio_duration = pendingAudio.duration;
            if (pendingAudio.bars) payload.audio_bars = pendingAudio.bars;
        }

        // Envoi optimiste : le message apparaît dès le clic, sans attendre le
        // serveur. On utilise un id temporaire (négatif), réconcilié avec le vrai
        // id quand la réponse arrive. L'heure/daté doivent suivre le même format
        // que le serveur (H:i et Y-m-d), sinon la bulle affiche un timestamp ISO.
        const nowLocal = new Date();
        const pad2 = (n) => (n < 10 ? '0' : '') + n;
        const tmpId = -(Date.now() % 0x7fffffff) - 1;
        const optimistic = {
            id: tmpId,
            sender_id: MY_ID,
            body,
            is_gif: hasGif,
            gif_url: pendingGif ? pendingGif.url : null,
            gif_alt: pendingGif ? pendingGif.alt : null,
            is_photo: !!pendingPhoto,
            photo_url: pendingPhoto ? pendingPhoto.url : null,
            photo_w: pendingPhoto && pendingPhoto.w ? pendingPhoto.w : null,
            photo_h: pendingPhoto && pendingPhoto.h ? pendingPhoto.h : null,
            is_audio: !!pendingAudio,
            audio_url: pendingAudio ? pendingAudio.url : null,
            audio_duration: pendingAudio ? pendingAudio.duration : null,
            audio_bars: pendingAudio ? (pendingAudio.bars || null) : null,
            lu: false,
            created_at: pad2(nowLocal.getHours()) + ':' + pad2(nowLocal.getMinutes()),
            date: nowLocal.getFullYear() + '-' + pad2(nowLocal.getMonth() + 1) + '-' + pad2(nowLocal.getDate()),
            reply_to: replyTarget ? {
                id: replyTarget.id,
                sender_id: null,
                sender_name: replyTarget.sender_name,
                body: replyTarget.body,
            } : null,
        };
        buildBubble(optimistic);
        scrollToBottom();
        setReply(null);
        pendingGif = null;
        pendingPhoto = null;
        hidePhotoPreview();
        closeGifPanel();
        // Envoi d'un vocal seul : on garde le texte déjà saisi (WhatsApp ne
        // l'efface pas). Sinon on vide l'input comme d'habitude.
        const voiceOnly = !!pendingAudio && !body && !hasGif && !optimistic.is_photo;
        if (!voiceOnly) {
            INPUT_EL.value = '';
        }
        autosize();
        SEND_BTN.disabled = true;
        // Retire ensuite le clavier et recolle le résultat en bas, une fois que
        // le clavier est réellement replié (sinon la barre remonte derrière lui).
        INPUT_EL.blur();
        window.setTimeout(function () {
            layout();
            scrollToBottom();
        }, 350);

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
                const msg = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + tmpId + '"]');
                if (msg) {
                    // Réconcilie le message affiché avec le vrai id du serveur.
                    msg.dataset.id = data.id;
                    renderedIds.delete(tmpId);
                    renderedIds.add(data.id);
                    if (data.id > lastMessageId) lastMessageId = data.id;
                }
            } else {
                toast(hasGif ? 'Erreur lors de l\'envoi du GIF.' : 'Erreur lors de l\'envoi.', 'error');
                rollbackOptimistic(tmpId);
            }
        } catch (e) {
            toast('Connexion perdue.', 'error');
            rollbackOptimistic(tmpId);
        } finally {
            sending = false;
            SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif && !pendingPhoto && !pendingAudio && !isMicRecording();
        }
    }

    // Retire la bulle optimiste (id temporaire) si l'envoi a échoué.
    function rollbackOptimistic(tmpId) {
        renderedIds.delete(tmpId);
        const msg = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + tmpId + '"]');
        if (msg) msg.remove();
    }

    function updateOnline(p) {
        if (!p) return;
        if (p.recording) {
            STATUS_EL.innerHTML = '<span style="color:var(--primary)">🎙 enregistrement d\u2019un audio…</span>';
        } else if (p.typing) {
            STATUS_EL.innerHTML = '<span style="color:var(--primary)">en train d\u2019\u00e9crire…</span>';
        } else if (p.enLigne) {
            STATUS_EL.innerHTML = '<span style="color:var(--success)">● en ligne</span>';
        } else if (p.present) {
            STATUS_EL.innerHTML = '<span class="muted">en ligne il y\'a ' + p.heure + '</span>';
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

    // Signale à l'autre que l'on enregistre un vocal ; le signal est rafraîchi
    // en continu pendant l'enregistrement (sinon l'indicateur expire en 3 s).
    let lastRecSignaled = 0;
    let recSignalTimer = null;
    function sendRecSignal() {
        const now = Date.now();
        if (now - lastRecSignaled < 1200) return;
        lastRecSignaled = now;
        fetch(RECORDING_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }).catch(() => {});
    }
    function startRecSignal() {
        stopRecSignal();
        sendRecSignal();
        recSignalTimer = setInterval(sendRecSignal, 1500);
    }
    function stopRecSignal() {
        if (recSignalTimer) {
            clearInterval(recSignalTimer);
            recSignalTimer = null;
        }
    }

    /* ---------- Panneau GIF + favoris ---------- */
    let gifRequest = null;
    let activeTab = 'search';
    let favCache = [];      // {id, url, alt} des favoris
    let favUrlSet = new Set();

    function hidePhotoPreview() {
        PHOTO_PREVIEW.style.display = 'none';
        PHOTO_PREVIEW_IMG.removeAttribute('src');
        PHOTO_INPUT.value = '';
    }

    /* ---------- Messages vocaux ---------- */

    function formatAudioTime(sec) {
        sec = Number(sec);
        if (!isFinite(sec) || sec < 0) return '0:00';
        sec = Math.round(sec);
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // Hauteurs (0-100) de la bande son stockées sur le message, complétées à n.
    function parseBars(str, n) {
        if (!str) return null;
        const arr = String(str).split(',').map((v) => parseInt(v, 10)).filter((v) => isFinite(v));
        if (arr.length < 2) return null;
        while (arr.length < n) arr.push(arr[arr.length - 1]);
        return arr.slice(0, n);
    }

    // Repli si la bande son est absente (ancien vocal) : motif pseudo-aléatoire
    // déterministe (même bande son pour l'expéditeur et le destinataire).
    function barsFromId(id, n) {
        let seed = ((Number(id) || 1) * 2654435761) % 100000;
        const arr = [];
        for (let i = 0; i < n; i++) {
            seed = (seed * 9301 + 49297) % 233280;
            arr.push(25 + Math.round((seed / 233280) * 55));
        }
        return arr;
    }

    // Avatar du partenaire (photo ou initiale) + badge micro en bas à droite,
    // affiché À L'INTÉRIEUR de la bulle vocale comme sur WhatsApp.
    function makeAvatar(msg) {
        const wrap = document.createElement('div');
        wrap.className = 'disc-vmsg-avatar';
        const img = document.createElement('div');
        img.className = 'disc-vmsg-avatar-img';
        if (msg.sender_photo_url) {
            img.style.backgroundImage = "url('" + msg.sender_photo_url + "')";
        } else {
            const colors = ['#E63946', '#F4A261', '#2A9D8F', '#E76F51', '#457B9D'];
            img.style.background = colors[(Number(msg.sender_id) || 0) % colors.length];
            img.textContent = ((msg.sender_name || '?').trim().charAt(0) || '?').toUpperCase();
        }
        wrap.appendChild(img);
        const badge = document.createElement('span');
        badge.className = 'disc-vmsg-badge';
        badge.setAttribute('aria-hidden', 'true');
        badge.innerHTML = ICON_MIC;
        wrap.appendChild(badge);
        return wrap;
    }

    // Durée réelle (en secondes) d'un vocal, décodée depuis son URL publique.
    // Les blobs de MediaRecorder n'ont pas de durée dans l'en-tête : le décodage
    // OfflineAudioContext donne la vraie durée pour l'affichage et la progression.
    function realAudioDuration(url) {
        const Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!Ctx) return Promise.resolve(0);
        return fetch(url, { credentials: 'same-origin' })
            .then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error('http'))))
            .then((buf) => new Promise((resolve) => {
                const ctx = new Ctx(1, 1, 44100);
                const finish = (b) => resolve(b.duration || 0);
                try {
                    const p = ctx.decodeAudioData(buf);
                    if (p && typeof p.then === 'function') p.then(finish).catch(() => resolve(0));
                    else ctx.decodeAudioData(buf.slice(0), finish, () => resolve(0));
                } catch (e) {
                    resolve(0);
                }
            }))
            .catch(() => 0);
    }

    function isMicRecording() {
        return micRecorder !== null;
    }

    function refreshSendBtn() {
        SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif && !pendingPhoto && !isMicRecording();
    }

    // Bande son « en direct » pendant l'enregistrement : barres pilotées par
    // l'AnalyserNode du flux micro (comme WhatsApp).
    function buildLiveWaves() {
        REC_WAVES.innerHTML = '';
        waveBars = [];
        for (let i = 0; i < 30; i++) {
            const b = document.createElement('span');
            b.className = 'disc-rec-wave';
            REC_WAVES.appendChild(b);
            waveBars.push(b);
        }
    }

    function startWaveAnim() {
        if (!micAnalyser) return;
        micFreq = micFreq || new Uint8Array(micAnalyser.frequencyBinCount);
        const tick = () => {
            micAnalyser.getByteFrequencyData(micFreq);
            const step = Math.max(1, Math.floor(micFreq.length / waveBars.length));
            for (let i = 0; i < waveBars.length; i++) {
                const v = micFreq[i * step] / 255;
                waveBars[i].style.height = (5 + Math.round(v * 28)) + 'px';
            }
            waveAnim = requestAnimationFrame(tick);
        };
        waveAnim = requestAnimationFrame(tick);
    }

    function stopWaveAnim() {
        if (waveAnim) cancelAnimationFrame(waveAnim);
        waveAnim = null;
    }

    function stopMicStream() {
        if (micStream) {
            micStream.getTracks().forEach((t) => t.stop());
            micStream = null;
        }
        if (micCtx && micCtx.state !== 'closed') micCtx.close().catch(() => {});
        micCtx = null;
        micAnalyser = null;
        micFreq = null;
    }

    function startRecording() {
        if (isMicRecording()) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            toast('Enregistrement non supporté sur cet appareil.', 'error');
            return;
        }
        closeGifPanel();
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then((stream) => {
                micStream = stream;
                micCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (micCtx.state === 'suspended') micCtx.resume().catch(() => {});
                micAnalyser = micCtx.createAnalyser();
                micAnalyser.fftSize = 256;
                micAnalyser.smoothingTimeConstant = 0.65;
                micCtx.createMediaStreamSource(stream).connect(micAnalyser);

                const mime = MediaRecorder.isTypeSupported('audio/webm')
                    ? 'audio/webm'
                    : (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '');
                let rec;
                try {
                    rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                } catch (e) {
                    rec = new MediaRecorder(stream);
                }
                micRecorder = rec;
                micChunks = [];
                rec.ondataavailable = (e) => { if (e.data && e.data.size > 0) micChunks.push(e.data); };
                rec.start(250);
                micStartedAt = Date.now();

                buildLiveWaves();
                startWaveAnim();
                COMPOSER_EL.classList.add('recording');
                REC_BAR.style.display = 'flex'; // override le style="display:none" inline
                REC_TIME.textContent = '0:00';
                micTimer = setInterval(() => {
                    const secs = Math.max(0, Math.round((Date.now() - micStartedAt) / 1000));
                    REC_TIME.textContent = formatAudioTime(secs);
                }, 250);
                refreshSendBtn();
                startRecSignal();
            })
            .catch(() => toast('Micro inaccessible : vérifiez le navigateur.', 'error'));
    }

    function restoreComposer() {
        COMPOSER_EL.classList.remove('recording');
        REC_BAR.style.display = 'none';
        stopWaveAnim();
        REC_TIME.textContent = '0:00';
        refreshSendBtn();
    }

    // Annuler : on jette l'enregistrement et on revient au composer normal.
    function cancelRecording() {
        if (!isMicRecording()) return;
        const rec = micRecorder;
        micRecorder = null;
        micChunks = [];
        if (rec && rec.state !== 'inactive') {
            rec.onstop = null;
            rec.stop();
        }
        clearInterval(micTimer);
        micTimer = null;
        stopMicStream();
        stopRecSignal();
        restoreComposer();
    }

    // Extrait les hauteurs (0-100) de la bande son réelle du vocal enregistré,
    // via decodeAudioData. Retourne '' si le calcul échoue (bande son générée côté affichage).
    function computeAudioBars(blob) {
        const Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!Ctx) return Promise.resolve('');
        return new Promise((resolve) => {
            const ctx = new Ctx(1, 1, 44100);
            const reader = new FileReader();
            reader.onload = () => {
                const finish = (buffer) => {
                    try {
                        const n = 36;
                        const channel = buffer.getChannelData(0);
                        const block = Math.floor(channel.length / n) || 1;
                        const bars = [];
                        for (let i = 0; i < n; i++) {
                            let sum = 0;
                            const start = i * block;
                            const end = Math.min(channel.length, start + block);
                            for (let j = start; j < end; j++) sum += Math.abs(channel[j]);
                            const avg = sum / Math.max(1, end - start);
                            bars.push(Math.max(10, Math.min(100, Math.round(avg * 220))));
                        }
                        resolve(bars.join(','));
                    } catch (e) {
                        resolve('');
                    }
                };
                try {
                    const p = ctx.decodeAudioData(reader.result);
                    if (p && typeof p.then === 'function') p.then(finish).catch(() => resolve(''));
                    else ctx.decodeAudioData(reader.result.slice(0), finish, () => resolve(''));
                } catch (e) {
                    resolve('');
                }
            };
            reader.onerror = () => resolve('');
            reader.readAsArrayBuffer(blob);
        });
    }

    async function uploadAudioBlob(blob) {
        const fd = new FormData();
        const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
        fd.append('audio', blob, 'vocal.' + ext);
        try {
            const res = await fetch(AUDIO_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: fd,
            });
            if (res.ok) return await res.json();
            const err = await res.json().catch(() => ({}));
            toast(err.message || 'Échec de l\'enregistrement vocal.', 'error');
        } catch (e) {
            toast('Connexion perdue.', 'error');
        }
        return null;
    }

    // Envoyer le vocal : on stoppe le recorder, on récupère la bande son réelle,
    // on upload le fichier puis on envoie le message immédiatement.
    async function sendRecording() {
        if (!isMicRecording()) return;
        REC_SEND.disabled = true;
        const rec = micRecorder;
        micRecorder = null;
        const blob = await new Promise((resolve) => {
            rec.onstop = () => resolve(new Blob(micChunks, { type: rec.mimeType || 'audio/webm' }));
            if (rec.state !== 'inactive') rec.stop();
        });
        micChunks = [];
        const duration = Math.max(1, Math.round((Date.now() - micStartedAt) / 1000));
        clearInterval(micTimer);
        micTimer = null;
        stopMicStream();
        stopRecSignal();
        restoreComposer();

        if (blob.size === 0) {
            REC_SEND.disabled = false;
            return;
        }
        try {
            const bars = await computeAudioBars(blob);
            const data = await uploadAudioBlob(blob);
            if (!data) return; // l'erreur a déjà été affichée
            pendingAudio = { path: data.path, url: data.url, duration, bars };
            await sendMessage();
        } finally {
            pendingAudio = null;
            REC_SEND.disabled = false;
        }
    }

    /* ---------- Visionneuse plein écran (style WhatsApp) ---------- */
    // Photos visibles dans la discussion : la visionneuse défile entre elles.
    let photoList = [];   // [{src, alt}]
    let photoIndex = -1;
    let swiped = false;   // un swipe a eu lieu → ne pas fermer la visionneuse au clic
    let swipeStartX = 0;
    let swipeStartY = 0;
    let swipeTracking = false;

    function showPhoto(i) {
        if (!photoList.length) return;
        if (i < 0) i = photoList.length - 1;
        if (i >= photoList.length) i = 0;
        photoIndex = i;
        const p = photoList[i];
        // Petit fondu à chaque changement de photo.
        PHOTO_VIEWER_IMG.style.animation = 'none';
        void PHOTO_VIEWER_IMG.offsetWidth;
        PHOTO_VIEWER_IMG.style.animation = 'fadeIn 0.18s ease';
        PHOTO_VIEWER_IMG.src = p.src;
        PHOTO_VIEWER_IMG.alt = p.alt || 'Photo';
        PHOTO_VIEWER_DOWNLOAD.href = p.src;
        try {
            // Nom de fichier pour le téléchargement, ex. "double-jeu-12345.jpg".
            const name = p.src.split('/').pop() || 'double-jeu.jpg';
            PHOTO_VIEWER_DOWNLOAD.download = 'double-jeu-' + name;
        } catch (e) { /* ignore */ }
        PHOTO_VIEWER_PREV.hidden = photoList.length < 2;
        PHOTO_VIEWER_NEXT.hidden = photoList.length < 2;
        PHOTO_VIEWER_COUNTER.textContent = photoList.length > 1
            ? (photoIndex + 1) + ' / ' + photoList.length
            : '';
    }

    function nextPhoto() { showPhoto(photoIndex + 1); }
    function prevPhoto() { showPhoto(photoIndex - 1); }

    function openPhotoViewer(src, alt) {
        // Toutes les photos actuellement dans le fil, dans l'ordre d'affichage.
        const imgs = Array.from(document.querySelectorAll('.disc-messages .disc-photo img'));
        photoList = imgs.map(img => ({ src: img.src, alt: img.alt || 'Photo' }));
        photoIndex = photoList.findIndex(p => p.src === src);
        if (photoIndex === -1) {
            // Photo non trouvée dans le fil (ex. pas encore rechargé) : on affiche seule.
            photoList = [{ src, alt: alt || 'Photo' }];
            photoIndex = 0;
        }
        showPhoto(photoIndex);
        PHOTO_VIEWER.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closePhotoViewer() {
        PHOTO_VIEWER.style.display = 'none';
        PHOTO_VIEWER_IMG.removeAttribute('src');
        photoList = [];
        photoIndex = -1;
        PHOTO_VIEWER_COUNTER.textContent = '';
        document.body.style.overflow = '';
    }
    PHOTO_VIEWER_CLOSE.addEventListener('click', closePhotoViewer);
    PHOTO_VIEWER_PREV.addEventListener('click', (e) => { e.stopPropagation(); prevPhoto(); });
    PHOTO_VIEWER_NEXT.addEventListener('click', (e) => { e.stopPropagation(); nextPhoto(); });
    PHOTO_VIEWER.addEventListener('click', (e) => {
        if (swiped) { swiped = false; return; }
        if (e.target === PHOTO_VIEWER) closePhotoViewer();
    });

    // Swipe gauche/droite pour naviguer entre les photos (iOS comme Android).
    PHOTO_VIEWER.addEventListener('pointerdown', (e) => {
        swipeStartX = e.clientX;
        swipeStartY = e.clientY;
        swipeTracking = true;
    });
    PHOTO_VIEWER.addEventListener('pointerup', (e) => {
        if (!swipeTracking) return;
        swipeTracking = false;
        const dx = e.clientX - swipeStartX;
        const dy = e.clientY - swipeStartY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.4) {
            swiped = true;
            if (dx < 0) nextPhoto(); else prevPhoto();
        }
    });
    PHOTO_VIEWER.addEventListener('pointercancel', () => { swipeTracking = false; });

    document.addEventListener('keydown', (e) => {
        if (PHOTO_VIEWER.style.display !== 'flex') return;
        if (e.key === 'Escape') closePhotoViewer();
        if (e.key === 'ArrowRight') nextPhoto();
        if (e.key === 'ArrowLeft') prevPhoto();
    });

    function closeGifPanel() {
        GIF_PANEL.style.display = 'none';
        GIF_SEARCH.value = '';
        GIF_GRID.innerHTML = '';
        GIF_BTN.classList.remove('active');
        SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif && !pendingPhoto;
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
        TAB_STICKERS.classList.toggle('active', tab === 'stickers');
        TAB_FAVS.classList.toggle('active', tab === 'favs');
        GIF_GRID.classList.toggle('disc-gif-grid-stickers', tab === 'stickers');
        if (tab === 'favs') {
            loadFavorites();
        } else if (tab === 'stickers') {
            loadStickers();
        } else {
            loadGifs(GIF_SEARCH.value.trim());
        }
    }

    async function loadStickers() {
        GIF_GRID.innerHTML = '<div class="disc-gif-loading">Chargement…</div>';
        try {
            const res = await fetch(STICKERS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                GIF_GRID.innerHTML = '<div class="disc-gif-error">Impossible de charger les stickers.</div>';
                return;
            }
            const data = await res.json();
            const items = (data.stickers || []).map(s => ({
                url: s.url,
                alt: s.alt || 'Sticker',
                preview: s.url,
                isFav: favUrlSet.has(s.url),
            }));
            renderGifGrid(items);
        } catch (e) {
            GIF_GRID.innerHTML = '<div class="disc-gif-error">Connexion perdue.</div>';
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
    TAB_STICKERS.addEventListener('click', () => showTab('stickers'));
    TAB_FAVS.addEventListener('click', () => showTab('favs'));

    GIF_BTN.addEventListener('click', () => {
        if (GIF_PANEL.style.display === 'flex') closeGifPanel();
        else openGifPanel();
    });

    /* ---------- Envoi de photo ---------- */
    CAMERA_BTN.addEventListener('click', () => {
        closeGifPanel();
        PHOTO_INPUT.accept = 'image/*';
        // Pas de capture : sur iOS, sans cet attribut le picker ouvre la
        // bibliothèque de photos (avec capture, il force l'appareil photo).
        PHOTO_INPUT.removeAttribute('capture');
        PHOTO_INPUT.removeAttribute('multiple');
        PHOTO_INPUT.click();
    });
    PHOTO_INPUT.addEventListener('change', async () => {
        const file = PHOTO_INPUT.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            toast('Image trop lourde (max 10 Mo).', 'error');
            PHOTO_INPUT.value = '';
            return;
        }
        const fd = new FormData();
        fd.append('photo', file);
        try {
            const res = await fetch(PHOTO_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });
            if (res.ok) {
                const data = await res.json();
                pendingPhoto = { path: data.path, url: data.url };
                PHOTO_PREVIEW_IMG.src = data.url;
                PHOTO_PREVIEW_IMG.onload = () => {
                    // On retient les dimensions natives : elles réservent la
                    // hauteur de la bulle sans attendre le chargement côté fil.
                    if (pendingPhoto) {
                        pendingPhoto.w = PHOTO_PREVIEW_IMG.naturalWidth || 0;
                        pendingPhoto.h = PHOTO_PREVIEW_IMG.naturalHeight || 0;
                    }
                };
                PHOTO_PREVIEW.style.display = 'flex';
                SEND_BTN.disabled = false;
                INPUT_EL.focus();
            } else {
                const err = await res.json().catch(() => ({}));
                toast(err.message || 'Photo invalide.', 'error');
                PHOTO_INPUT.value = '';
            }
        } catch (e) {
            toast('Connexion perdue.', 'error');
            PHOTO_INPUT.value = '';
        }
    });
    PHOTO_PREVIEW_CLOSE.addEventListener('click', () => {
        pendingPhoto = null;
        hidePhotoPreview();
        refreshSendBtn();
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
        refreshSendBtn();
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

    // Enregistrement vocal : le micro ouvre la barre d'enregistrement.
    MIC_BTN.addEventListener('click', () => {
        if (isMicRecording()) {
            return;
        }
        startRecording();
    });
    REC_CANCEL.addEventListener('click', cancelRecording);
    REC_SEND.addEventListener('click', sendRecording);

    // "Pré-rendu" côté client : les messages injectés par le serveur dans
    // #disc-init-messages sont construits avec buildBubble (le MÊME rendu que le
    // fetch) pendant le parse, donc avant la première peinture. Aucun HTML de
    // bulle n'est écrit côté serveur : l'affichage ne peut pas différer.
    // La zone reste invisible (visibility:hidden) seulement le temps de construire
    // toutes les bulles de façon synchrone pendant le parse. Les photos ont une
    // hauteur réservée (aspect-ratio) → le chargement ne peut plus décaler le
    // fil : on révèle d'un coup, sur la hauteur finale, avant la première
    // peinture → ouverture directe sur le dernier message, sans défilement.
    let discRevealed = false;
    function revealDisc() {
        if (discRevealed) return;
        discRevealed = true;
        initialLoad = false;
        MESSAGES_EL.style.visibility = 'visible';
        MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        // Juste avant la première peinture, on re-vérifie l'ancrage : si le
        // layout s'est encore affiné (polices…), la correction est invisible.
        requestAnimationFrame(() => {
            MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        });
    }
    function bootPrerendered() {
        const el = document.getElementById('disc-init-messages');
        let list = [];
        if (el) {
            try {
                list = JSON.parse(el.textContent) || [];
            } catch (err) {
                list = [];
            }
            for (const m of list) buildBubble(m);
        }
        const pending = Array.from(
            MESSAGES_EL.querySelectorAll('.disc-photo img, .disc-gif img')
        ).filter((img) => !img.complete && !img.style.aspectRatio);

        if (pending.length === 0) {
            revealDisc();
            return;
        }
        // Seules les images SANS hauteur réservée (GIF, très vieilles photos)
        // peuvent encore décaler le fil : on les charge en arrière-plan (zone
        // invisible), on attend qu'elles soient posées, puis on révèle. Leur
        // nombre est faible, l'attente est donc courte.
        let left = pending.length;
        const done = () => {
            left -= 1;
            if (left <= 0) revealDisc();
        };
        for (const img of pending) {
            img.loading = 'eager';
            img.addEventListener('load', done);
            img.addEventListener('error', done);
        }
        // Filet de sécurité : on révèle de toute façon après 8 s.
        setTimeout(() => {
            if (left > 0) revealDisc();
        }, 8000);
    }

    // Mobile : la barre d'URL se replie ~0,5 s après l'arrivée et agrandit le
    // viewport, les polices web s'installent aussi après coup. Chaque changement
    // de layout décalerait l'ancrage du bas. Tant qu'on est (toujours) en bas,
    // on re-colle en une fois, sans animation, à chaque reflow : c'est un simple
    // ré-ancrage silencieux, jamais un défilement visible.
    function glueIfAtBottom() {
        if (wasAtBottom()) {
            MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
        }
    }
    window.addEventListener('resize', glueIfAtBottom);
    window.addEventListener('orientationchange', glueIfAtBottom);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', glueIfAtBottom);
    }
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(glueIfAtBottom);
    }

    // Poll unique : messages + statut en ligne en une seule requête (1,5 s).
    bootPrerendered();
    fetchMessages();
    setInterval(fetchMessages, 1500);

    // Pas de focus automatique à l'entrée : garder stable la position de la
    // barre d'envoi (sur mobile, le focus ouvrirait le clavier et ferait
    // remonter le composer). Le focus est remis après une action (envoi, photo…).
})();
</script>
@endpush
