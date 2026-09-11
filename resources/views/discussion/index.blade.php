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
    /* Icônes d'appel (décoratives) en haut à droite du header, façon WhatsApp. */
    .disc-call-icons {
        display: flex;
        align-items: center;
        gap: 2px;
        margin-right: -4px;
        position: relative;
    }
    .disc-call-btn {
        width: 40px;
        height: 40px;
        border: none;
        background: transparent;
        border-radius: 50%;
        color: var(--primary-2);
        cursor: pointer;
        display: grid;
        place-items: center;
        transition: 0.2s;
    }
    .disc-call-btn:hover { background: var(--card-2); }
    .disc-call-btn:active { transform: scale(0.92); }
    /* Le badge des non-lus se pose sur les icônes (ancré dans .disc-call-icons). */
    .disc-badge {
        position: absolute;
        top: 2px;
        right: -2px;
        z-index: 2;
    }
    /* Modal info "bientôt disponible" : voile + carte centrée, au-dessus du reste. */
    .disc-call-modal {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: grid;
        place-items: center;
    }
    .disc-call-modal-card {
        position: relative;
        z-index: 81;
        width: min(320px, 86vw);
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 28px 24px 22px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        text-align: center;
    }
    .disc-call-modal-icon { font-size: 34px; }
    .disc-call-modal-title { font-size: 17px; font-weight: 700; }
    .disc-call-modal-text { font-size: 13px; color: var(--text-3); }
    .disc-call-modal-ok {
        margin-top: 8px;
        padding: 10px 26px;
        border: none;
        border-radius: 999px;
        background: var(--primary);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .disc-call-modal-ok:active { transform: scale(0.95); }
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
            <div class="disc-call-icons" id="disc-call-icons">
                <button type="button" class="disc-call-btn" id="disc-call-voice" aria-label="Appel vocal" title="Appel vocal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </button>
                <button type="button" class="disc-call-btn" id="disc-call-video" aria-label="Appel vidéo" title="Appel vidéo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                </button>
            </div>
        </div>

        {{-- Modal info : appels pas encore disponibles (icônes décoratives) --}}
        <div class="disc-call-modal" id="disc-call-modal" style="display:none" role="dialog" aria-modal="true">
            <div class="disc-backdrop" data-call-close></div>
            <div class="disc-call-modal-card">
                <div class="disc-call-modal-icon">🔮</div>
                <div class="disc-call-modal-title" id="disc-call-modal-title">Appel vocal</div>
                <div class="disc-call-modal-text">Cette fonctionnalité sera bientôt disponible !</div>
                <button type="button" class="disc-call-modal-ok" data-call-close>OK</button>
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
                <div class="disc-reply-row" id="disc-reply-row">
                    <div class="disc-reply-thumb-host"></div>
                    <div class="disc-reply-body" id="disc-reply-body"></div>
                </div>
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
            <button class="disc-camera-btn" id="disc-camera-btn" type="button" aria-label="Envoyer une photo ou une vidéo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                </svg>
            </button>
            <input type="file" id="disc-photo-input" accept="image/*,video/*" style="display:none">
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
            <button class="disc-send" id="disc-send" disabled aria-label="Envoyer avec amour">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
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

    {{-- Écran de prévisualisation avant envoi (façon WhatsApp) : couvre toute la
         page de la discussion ; le média + une légende + l'envoi s'y font. --}}
    <div class="disc-send-sheet" id="disc-send-sheet" style="display:none">
        <div class="disc-send-sheet-head">
            <button type="button" class="disc-send-sheet-back" id="disc-send-sheet-back" aria-label="Annuler">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <span class="disc-send-sheet-title">Aperçu</span>
        </div>
        <div class="disc-send-sheet-media">
            <div class="disc-send-sheet-loader" id="disc-send-sheet-loader" style="display:none">
                <span class="disc-send-sheet-spinner" aria-hidden="true"></span>
                <span>Chargement de l'aperçu… veuillez patienter</span>
            </div>
            <img id="disc-send-sheet-img" alt="Aperçu de la photo" style="display:none">
            <video id="disc-send-sheet-video" playsinline preload="metadata" controls style="display:none"></video>
            <span class="disc-send-sheet-chip" id="disc-send-sheet-chip" style="display:none"></span>
        </div>
        <div class="disc-send-sheet-foot">
            <div class="disc-send-caption">
                <input type="text" id="disc-send-caption" placeholder="Ajouter une légende…" maxlength="2000" autocomplete="off" enterkeyhint="send" />
            </div>
            <button type="button" class="disc-send-sheet-send" id="disc-send-sheet-send" aria-label="Envoyer">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </button>
        </div>
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
    const SEND_SHEET = document.getElementById('disc-send-sheet');
    const SEND_SHEET_LOADER = document.getElementById('disc-send-sheet-loader');
    const SEND_SHEET_BACK = document.getElementById('disc-send-sheet-back');
    const SEND_SHEET_IMG = document.getElementById('disc-send-sheet-img');
    const SEND_SHEET_VIDEO = document.getElementById('disc-send-sheet-video');
    const SEND_SHEET_CHIP = document.getElementById('disc-send-sheet-chip');
    const SEND_SHEET_CAPTION = document.getElementById('disc-send-caption');
    const SEND_SHEET_SEND = document.getElementById('disc-send-sheet-send');
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
    const REPLY_ROW_EL = document.getElementById('disc-reply-row');
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
    const VIDEO_URL = '{{ route("discussion.video") }}';
    const AUDIO_URL = '{{ route("discussion.audio") }}';
    const TYPING_URL = '{{ route("discussion.typing") }}';
    const RECORDING_URL = '{{ route("discussion.recording") }}';
    const GIFS_URL = '{{ route("discussion.gifs") }}';
    const STICKERS_URL = '{{ route("discussion.stickers") }}';
    const FAVORITES_URL = '{{ route("discussion.favorites") }}';
    const FAVORITES_TOGGLE_URL = '{{ route("discussion.favorites.toggle") }}';
    const DELETE_URL = '/discussion/message/';
    const MY_ID = {{ $me->id }};
    const MY_NAME = @json($me->name);
    const MY_AVATAR_URL = @json($me->avatar_url ? '/storage/'.$me->avatar_url : null);
    const PARTNER_NAME = @json($partenaire->name);
    const ICON_PLAY = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
    const ICON_PAUSE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';
    const ICON_MIC = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M18 11a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V21h2v-2.07A8 8 0 0 0 20 11h-2z"/></svg>';

    // Ensemble des id déjà rendus pour éviter tout doublon.
    const renderedIds = new Set();
    let lastMessageId = 0;
    // Correspondance id temporaire (optimiste) → id serveur, pour retrouver un
    // message envoyé juste avant sa réconciliation (ex. suppression immédiate).
    const tmpToRealId = new Map();
    // Pré-rendu « derniers messages d'abord » : on construit de façon synchrone
    // uniquement la fin du fil (ce qui est visible en arrivant), puis les plus
    // anciens s'empilent AU-DESSUS par lots (rAF). bootAnchor est le point
    // d'insertion invisible qui sépare l'historique construit des suivants ;
    // prefillDone est faux tant que l'historique injecté n'est pas tout rendu.
    let bootAnchor = null;
    let prefillDone = true;
    let sending = false;
    let replyTarget = null; // {id, sender_name, body, is_gif, gif_url, is_photo, photo_url, is_video, video_url, video_poster_url}
    let pendingGif = null; // {url, alt} sélectionné dans le panneau GIF
    let pendingPhoto = null; // {path, url} photo choisie à envoyer
    let pendingVideo = null; // {path, url, w, h, posterPath, posterUrl} vidéo choisie à envoyer
    let pendingCaption = null; // légende éditée dans l'écran de prévisualisation avant envoi
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

    // Le serveur envoie un timestamp UTC : chaque lecteur doit voir l'heure de
    // SON propre fuseau (ex. France vs Sénégal). On dérive ici l'heure et la
    // date locales depuis le timestamp ; un message déjà créé côté client
    // (optimiste) est déjà en heure locale et n'est pas retouché.
    function localizeMsg(msg) {
        if (!msg || /^\d{2}:\d{2}$/.test(msg.created_at || '')) return msg;
        if (msg.created_at) {
            const d = new Date(msg.created_at);
            if (!isNaN(d.getTime())) {
                const p2 = (n) => (n < 10 ? '0' : '') + n;
                msg.created_at = p2(d.getHours()) + ':' + p2(d.getMinutes());
                msg.date = d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate());
            }
        }
        return msg;
    }

    function escHtml(s) {
        const el = document.createElement('div');
        el.textContent = s;
        return el.innerHTML;
    }

    /* ---------- Répondre à un message ---------- */
    // Pour un message cité en réponse, on mémorise aussi le média : cela permet
    // d'afficher la vignette (stickers/photo/vidéo) dans la barre « Répondre » et
    // dans la bulle citée du destinataire (msg.body est vide pour ces types).
    function quoteSnapshot(msg) {
        if (!msg) return null;
        return {
            id: msg.id,
            sender_id: msg.sender_id ?? null,
            sender_name: msg.sender_name,
            body: msg.body,
            is_gif: !!msg.is_gif,
            gif_url: msg.gif_url || null,
            is_photo: !!msg.is_photo,
            photo_url: msg.photo_url || null,
            is_video: !!msg.is_video,
            video_url: msg.video_url || null,
            video_poster_url: msg.video_poster_url || null,
            is_audio: !!msg.is_audio,
            audio_duration: msg.audio_duration || null,
        };
    }

    // Libellé textuel le plus court représentant le message cité (pour les
    // types sans corps : sticker/photo/vidéo/vocal).
    function quoteLabel(q) {
        if (q && q.body) return q.body;
        if (q && q.is_gif) return 'Sticker';
        if (q && q.is_photo) return '📷 Photo';
        if (q && q.is_video) return '🎬 Vidéo';
        if (q && q.is_audio) return '🎤 Vocal : ' + formatAudioTime(q.audio_duration);
        return '';
    }

    // Source de la miniature à afficher pour le message cité. Seuls le sticker
    // et la photo ont une vraie image (petite). La vidéo n'a volontairement pas
    // de miniature : on affiche juste une petite icône ▶ sur fond sombre (façon
    // WhatsApp), jamais la vraie vignette qui est lourde et hors de propos à
    // cette taille.
    function quoteThumbSrc(q) {
        if (!q) return null;
        if (q.is_gif && q.gif_url) return q.gif_url;
        if (q.is_photo && q.photo_url) return q.photo_url;
        return null;
    }

    // Construit la miniature (sticker/photo) OU le petit repère vidéo ▶ (vocal :
    // rien). Renvoie null quand il n'y a aucun média à montrer.
    function quoteThumbNode(q, kindCls) {
        if (!q) return null;
        if (q.is_video) {
            const box = document.createElement('span');
            box.className = 'disc-quote-thumb disc-quote-thumb-video' + (kindCls || '');
            box.setAttribute('aria-hidden', 'true');
            return box;
        }
        const src = quoteThumbSrc(q);
        if (!src) return null;
        const img = document.createElement('img');
        img.className = 'disc-quote-thumb disc-quote-thumb-img' + (kindCls || '');
        img.src = src;
        img.alt = quoteLabel(q);
        img.loading = 'lazy';
        return img;
    }

    // Scroll vers le message cité (façon WhatsApp) + anneau de repérage. Si la
    // bulle n'est pas encore construite (pré-rendu des plus anciens en cours),
    // on réessaie pendant quelques secondes.
    function gotoMessage(id, attempts) {
        const tries = attempts || 0;
        const wrap = MESSAGES_EL.querySelector('.disc-bubble-wrap[data-id="' + id + '"]');
        if (!wrap) {
            if (tries < 20) setTimeout(() => gotoMessage(id, tries + 1), 200);
            return;
        }
        MESSAGES_EL.querySelector('.disc-bubble-wrap.disc-flash')?.classList.remove('disc-flash');
        wrap.classList.add('disc-flash');
        setTimeout(() => wrap.classList.remove('disc-flash'), 2400);
        const top = wrap.offsetTop - MESSAGES_EL.clientHeight / 2 + wrap.offsetHeight / 2;
        MESSAGES_EL.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function setReply(msg) {
        replyTarget = quoteSnapshot(msg);
        const host = REPLY_ROW_EL.querySelector('.disc-reply-thumb-host');
        host && (host.innerHTML = '');
        if (replyTarget) {
            REPLY_NAME_EL.textContent = '↩️ Répondre à ' + (replyTarget.sender_name || '…');
            const node = quoteThumbNode(replyTarget, ' disc-reply-thumb');
            if (node && host) host.appendChild(node);
            REPLY_BODY_EL.textContent = quoteLabel(replyTarget);
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

    // L'id d'un message optimiste est négatif (temporaire) jusqu'à la réponse
    // du serveur. Pour une action (suppression) effectuée avant réconciliation,
    // on retourne l'id serveur via tmpToRealId ; sinon on garde l'id tel quel.
    function resolveServerId(id) {
        if (Number(id) > 0) return id;
        return tmpToRealId.get(id) ?? null;
    }

    async function deleteMessages(ids, mode) {
        let failed = false;
        for (const rawId of ids) {
            const id = await resolveServerId(rawId);
            if (id == null) continue;
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
                        selectedMsgs.delete(rawId);
                        selectedMsgs.delete(id);
                    } else {
                        if (wrap) wrap.remove();
                        selectedMsgs.delete(rawId);
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
        localizeMsg(msg);
        if (renderedIds.has(msg.id)) {
            return;
        }
        renderedIds.add(msg.id);
        if (msg.id > lastMessageId) lastMessageId = msg.id;

        const isMe = String(msg.sender_id) === String(MY_ID);

        // Les messages plus anciens que le point d'ancrage s'insèrent AVANT lui
        // (au-dessus du fil) ; les autres (id positif plus grand, y compris les
        // nouveaux et les optimistes, dont l'id est négatif) sont ajoutés en fin.
        // L'ordre chronologique est donc toujours respecté, même quand
        // l'historique est construit en plusieurs passes.
        const before = (bootAnchor && Number(msg.id) > 0 && Number(msg.id) < Number(bootAnchor.dataset.id))
            ? bootAnchor
            : null;

        // Retire le bloc d'accueil dès le premier message rendu.
        const welcome = MESSAGES_EL.querySelector('.disc-welcome');
        if (welcome) welcome.remove();

        // Le 1er message du pré-rendu n'a pas de séparateur si un historique
        // plus ancien (non encore construit) le précède : le drapeau est
        // consommé au premier buildBubble.
        const bootNoSep = MESSAGES_EL.dataset.bootNoSep === '1';
        if (bootNoSep) delete MESSAGES_EL.dataset.bootNoSep;
        if (!bootNoSep && msg.date !== msgDateBefore(before)) {
            const sep = document.createElement('div');
            sep.className = 'disc-date-sep';
            sep.innerHTML = '<span>' + escHtml(formatDate(msg.date)) + '</span>';
            if (before) MESSAGES_EL.insertBefore(sep, before);
            else MESSAGES_EL.appendChild(sep);
        }

        const wrap = document.createElement('div');
        wrap.className = 'disc-bubble-wrap ' + (isMe ? 'me' : 'them');
        wrap.dataset.id = msg.id;
        wrap.dataset.date = msg.date;

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
            if (before) MESSAGES_EL.insertBefore(wrap, before);
            else MESSAGES_EL.appendChild(wrap);
            return;
        }

        // Message cité (réponse) au-dessus du texte.
        if (msg.reply_to) {
            const quoted = document.createElement('div');
            quoted.className = 'disc-quoted';
            const qRow = document.createElement('div');
            qRow.className = 'disc-quoted-row';
            const qName = document.createElement('div');
            qName.className = 'disc-quoted-name';
            const meSaid = String(msg.reply_to.sender_id) === String(MY_ID);
            const who = meSaid ? 'Toi' : (msg.reply_to.sender_name || '…');
            qName.textContent = '↪️ ' + who + ' : ' + quoteLabel(msg.reply_to);
            const qThumb = quoteThumbNode(msg.reply_to, ' disc-quoted-thumb');
            if (qThumb) qRow.appendChild(qThumb);
            qRow.appendChild(qName);
            quoted.appendChild(qRow);
            // Clic sur la citation → scroll vers le message cité (façon WhatsApp).
            quoted.setAttribute('role', 'button');
            quoted.setAttribute('aria-label', 'Voir le message cité');
            quoted.addEventListener('click', (e) => {
                e.stopPropagation();
                gotoMessage(msg.reply_to.id);
            });
            // Le tap/long-press sur la citation n'ouvre PAS la sélection de la
            // bulle : on ne fait que sauter vers le message cité.
            quoted.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
            quoted.addEventListener('contextmenu', (e) => e.stopPropagation());
            bubble.appendChild(quoted);
        }

        // Message GIF/sticker : grande image au lieu du texte. Taille fixe réservée
        // (carré, object-fit:contain, aucune découpe) → la hauteur ne bouge plus,
        // quel que soit le navigateur (même ancien, sans aspect-ratio).
        if (msg.is_gif && msg.gif_url) {
            const imgWrap = document.createElement('div');
            imgWrap.className = 'disc-gif';
            const img = document.createElement('img');
            img.src = msg.gif_url;
            img.alt = msg.gif_alt || 'GIF';
            img.loading = 'lazy';
            const gifW = Math.min(220, Math.max(120, Math.round((window.innerWidth || 360) * 0.6)));
            img.style.width = gifW + 'px';
            img.style.height = gifW + 'px';
            img.style.objectFit = 'contain';
            imgWrap.appendChild(img);
            bubble.appendChild(imgWrap);
        }

        // Message photo : image hébergée localement. La taille native étant connue,
        // on réserve width/height en pixels (compatibles avec les navigateurs
        // mobiles qui n'ont pas aspect-ratio) → le chargement ne peut plus
        // décaler le fil : plus de défilement à l'ouverture.
        if (msg.is_photo && msg.photo_url) {
            const imgWrap = document.createElement('div');
            imgWrap.className = 'disc-photo';
            const img = document.createElement('img');
            img.src = msg.photo_url;
            img.alt = 'Photo';
            if (msg.photo_w && msg.photo_h) {
                const maxW = Math.min(260, Math.max(120, Math.round((window.innerWidth || 360) * 0.6)));
                let w = maxW;
                let h = Math.round((w * msg.photo_h) / msg.photo_w);
                const maxH = Math.round(maxW * 1.4);
                if (h > maxH) {
                    h = maxH;
                    w = Math.round((h * msg.photo_w) / msg.photo_h);
                }
                img.style.width = w + 'px';
                img.style.height = h + 'px';
            }
            img.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openPhotoViewer(img.src, img.alt);
            });
            imgWrap.appendChild(img);
            bubble.appendChild(imgWrap);
        }

        // Message vidéo : lecteur natif dans la bulle (façon WhatsApp). La taille
        // native est réservée comme pour les photos → ni le fil ni la lecture ne
        // décalent le contenu. Un bouton play recouvre la vignette (première frame,
        // chargée via preload=metadata) tant que la vidéo est en pause.
        if (msg.is_video && msg.video_url) {
            const vidWrap = document.createElement('div');
            vidWrap.className = 'disc-video';
            const video = document.createElement('video');
            video.src = msg.video_url;
            video.preload = 'metadata';
            video.setAttribute('playsinline', '');
            video.controls = true;
            video.setAttribute('aria-label', 'Vidéo');
            // Poster = miniature de la première frame (générée par l'expéditeur) :
            // iOS ne décode aucune image d'un <video> sans poster → cadre noir.
            if (msg.video_poster_url) video.poster = msg.video_poster_url;
            if (msg.video_w && msg.video_h) {
                const maxW = Math.min(260, Math.max(120, Math.round((window.innerWidth || 360) * 0.6)));
                let w = maxW;
                let h = Math.round((w * msg.video_h) / msg.video_w);
                const maxH = Math.round(maxW * 1.4);
                if (h > maxH) {
                    h = maxH;
                    w = Math.round((h * msg.video_w) / msg.video_h);
                }
                video.style.width = w + 'px';
                video.style.height = h + 'px';
            }
            const play = document.createElement('button');
            play.type = 'button';
            play.className = 'disc-video-play';
            play.setAttribute('aria-label', 'Lire la vidéo');
            play.innerHTML = ICON_PLAY;
            // Le bouton central lance la lecture et disparaît ; il revient en
            // pause. Le tap sur la vidéo fait aussi play/pause. Les événements
            // sont isolés de la sélection/long-press de la bulle (wireMessage).
            play.addEventListener('click', () => {
                video.play();
            });
            video.addEventListener('play', () => { play.style.display = 'none'; });
            video.addEventListener('pause', () => { play.style.display = 'grid'; });
            video.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
            });
            vidWrap.appendChild(video);
            vidWrap.appendChild(play);
            bubble.appendChild(vidWrap);
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
        if (before) MESSAGES_EL.insertBefore(wrap, before);
        else MESSAGES_EL.appendChild(wrap);

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

    // Date du message rendu juste avant un point d'insertion (ou dernière bulle
    // du fil pour un ajout en fin). On remonte les .disc-bubble-wrap ; les
    // séparateurs de date sont ignorés car seule la bulle compte : un séparateur
    // est posé quand la date change par rapport au message voisin RÉEL dans le
    // DOM. Robuste quel que soit l'ordre des passes de construction.
    function msgDateBefore(before) {
        let node = before ? before.previousElementSibling : MESSAGES_EL.lastElementChild;
        while (node) {
            if (node.classList && node.classList.contains('disc-bubble-wrap')) {
                return node.dataset.date || '';
            }
            node = node.previousElementSibling;
        }
        return '';
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
                if (!prefillDone) {
                    // Pendant le pré-rendu des plus anciens (lots rAF), on ne
                    // construit RIEN ici : le batch les insère dans l'ordre au
                    //-dessus du fil. On synchronise seulement l'état "lu" des
                    // bulles déjà affichées.
                    syncReadState(data.messages);
                } else {
                    for (const msg of data.messages) {
                        buildBubble(msg);
                    }
                    syncReadState(data.messages);
                    // Au tout premier chargement on colle directement tout en bas
                    // (scroll instantané), sinon on reste en bas de façon animée à
                    // chaque poll.
                    if (initialLoad) {
                        scrollToBottom();
                        stickyToBottomOnce();
                    } else if (bottom || hasIncoming) {
                        scrollToBottom();
                    }
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
                document.getElementById('disc-call-icons')?.appendChild(badge);
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

    // Petit son d'envoi (façon WhatsApp/sent moderne) : généré par Web Audio, sans
    // fichier audio. Simple retour sonore au moment où l'on envoie (aucun rapport
    // avec le son des notifications système).
    let sendCtx = null;
    // iOS : un AudioContext créé ou repris hors d'un geste utilisateur reste
    // suspendu (aucun son). On le crée et le déverrouille dès le TOUT PREMIER
    // geste sur la page (même un tap sans rapport) : pour le premier envoi, le
    // contexte est déjà prêt à jouer.
    function unlockSendAudio() {
        if (sendCtx) return;
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        try {
            sendCtx = new AC();
            sendCtx.resume().catch(() => {});
        } catch (e) {
            sendCtx = null;
        }
    }
    ['pointerdown', 'touchstart', 'keydown'].forEach((ev) =>
        document.addEventListener(ev, () => unlockSendAudio(), { once: true, passive: true })
    );
    function playSendSound() {
        if (!sendCtx) unlockSendAudio();
        if (!sendCtx) return;
        try {
            if (sendCtx.state === 'suspended') sendCtx.resume().catch(() => {});
            // On joue même si l'état n'est pas encore 'running' : sur iOS la
            // reprise est asynchrone, les notes partiront dès que possible (petit
            // décalage imperceptible, jamais de son perdu).
            const t = sendCtx.currentTime + 0.02;
            const osc = sendCtx.createOscillator();
            const gain = sendCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(520, t);
            osc.frequency.exponentialRampToValueAtTime(980, t + 0.1);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.09, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.2);
            osc.connect(gain).connect(sendCtx.destination);
            osc.start(t);
            osc.stop(t + 0.3);
        } catch (e) { /* ignore */ }
    }

    async function sendMessage() {
        // Depuis l'écran de prévisualisation, le texte envoyé est la légende.
        const sheetOpen = (pendingPhoto || pendingVideo) && SEND_SHEET.style.display !== 'none';
        const body = sheetOpen ? SEND_SHEET_CAPTION.value.trim() : INPUT_EL.value.trim();
        if ((!body && !pendingGif && !pendingPhoto && !pendingVideo && !pendingAudio) || sending) return;

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
        if (pendingVideo) {
            payload.video_path = pendingVideo.path;
            if (pendingVideo.w && pendingVideo.h) {
                payload.video_w = pendingVideo.w;
                payload.video_h = pendingVideo.h;
            }
            if (pendingVideo.posterPath) payload.video_poster_path = pendingVideo.posterPath;
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
            sender_name: MY_NAME,
            sender_photo_url: MY_AVATAR_URL,
            body,
            is_gif: hasGif,
            gif_url: pendingGif ? pendingGif.url : null,
            gif_alt: pendingGif ? pendingGif.alt : null,
            is_photo: !!pendingPhoto,
            photo_url: pendingPhoto ? pendingPhoto.url : null,
            photo_w: pendingPhoto && pendingPhoto.w ? pendingPhoto.w : null,
            photo_h: pendingPhoto && pendingPhoto.h ? pendingPhoto.h : null,
            is_video: !!pendingVideo,
            video_url: pendingVideo ? pendingVideo.url : null,
            video_w: pendingVideo && pendingVideo.w ? pendingVideo.w : null,
            video_h: pendingVideo && pendingVideo.h ? pendingVideo.h : null,
            video_poster_url: pendingVideo ? (pendingVideo.posterUrl || null) : null,
            is_audio: !!pendingAudio,
            audio_url: pendingAudio ? pendingAudio.url : null,
            audio_duration: pendingAudio ? pendingAudio.duration : null,
            audio_bars: pendingAudio ? (pendingAudio.bars || null) : null,
            lu: false,
            created_at: pad2(nowLocal.getHours()) + ':' + pad2(nowLocal.getMinutes()),
            date: nowLocal.getFullYear() + '-' + pad2(nowLocal.getMonth() + 1) + '-' + pad2(nowLocal.getDate()),
            reply_to: replyTarget ? {
                id: replyTarget.id,
                sender_id: replyTarget.sender_id,
                sender_name: replyTarget.sender_name,
                body: replyTarget.body,
                is_gif: replyTarget.is_gif,
                gif_url: replyTarget.gif_url,
                is_photo: replyTarget.is_photo,
                photo_url: replyTarget.photo_url,
                is_video: replyTarget.is_video,
                video_url: replyTarget.video_url,
                video_poster_url: replyTarget.video_poster_url,
                is_audio: replyTarget.is_audio,
            } : null,
        };
        buildBubble(optimistic);
        scrollToBottom();
        playSendSound();
        setReply(null);
        pendingGif = null;
        pendingPhoto = null;
        pendingVideo = null;
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
                    tmpToRealId.set(tmpId, data.id);
                    renderedIds.delete(tmpId);
                    renderedIds.add(data.id);
                    if (data.id > lastMessageId) lastMessageId = data.id;
                } else {
                    tmpToRealId.set(tmpId, data.id);
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
            SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif && !pendingPhoto && !pendingVideo && !pendingAudio && !isMicRecording();
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
            STATUS_EL.innerHTML = '<span style="color:var(--primary)">🎙 Enregistrement…</span>';
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

    /* ---------- Prévisualisation pleine page avant envoi (façon WhatsApp) ---------- */
    let mediaSheetHideTimer = null;
    let mediaSheetExiting = false;

    // Ouverture de l'écran de prévisualisation à la place du composer.
    function openMediaSheet() {
        // Le texte saisi dans le composer devient la légende initiale du média
        // (comme WhatsApp) afin qu'aucun texte ne soit perdu.
        const composed = INPUT_EL.value.trim();
        if (composed) {
            SEND_SHEET_CAPTION.value = composed;
            pendingCaption = composed;
            INPUT_EL.value = '';
            autosize();
            refreshSendBtn();
        } else {
            SEND_SHEET_CAPTION.value = pendingCaption || '';
        }
        SEND_SHEET.style.display = 'flex';
        SEND_SHEET.classList.remove('disc-send-sheet-show');
        // petite frame pour que le navigateur amarre l'animation d'entrée
        SEND_SHEET.offsetHeight;
        SEND_SHEET.classList.add('disc-send-sheet-show');
        updateSheetControls();
        if (SEND_SHEET.requestFullscreen) {
            SEND_SHEET.requestFullscreen().catch(() => {});
        }
        bindSheetKeyboardLayout();
        layoutSheetForKeyboard();
        setTimeout(() => SEND_SHEET_CAPTION.focus(), 60);
    }

    // Remet la légende (non envoyée) dans le composer après annulation.
    function restoreCaptionToComposer() {
        const caption = SEND_SHEET_CAPTION.value.trim();
        if (!caption) return;
        const existing = INPUT_EL.value;
        INPUT_EL.value = existing ? existing + '\n' + caption : caption;
        autosize();
        refreshSendBtn();
    }

    // Bouton d'envoi + chip d'état de la vidéo dans l'écran de prévisualisation.
    // La ligne affiche « taille · durée » à gauche du statut (ex. « 37 Mo · 0:21 ·
    // Vidéo prête »), comme demandé.
    function updateSheetControls() {
        let ready = true;
        if (pendingVideo) ready = !pendingVideo.uploading && !pendingVideo.posterPending;
        if (pendingPhoto) ready = !pendingPhoto.uploading;
        SEND_SHEET_SEND.disabled = !ready;
        if (pendingVideo && !pendingPhoto) {
            SEND_SHEET_CHIP.style.display = 'block';
            let status;
            if (pendingVideo.uploading) {
                status = 'Envoi de la vidéo… veuillez patienter';
            } else if (pendingVideo.posterPending) {
                status = 'Préparation de la vignette…';
            } else {
                status = 'Vidéo prête';
            }
            const info = [pendingVideo.sizeLabel, pendingVideo.durationLabel].filter(Boolean).join(' · ');
            SEND_SHEET_CHIP.textContent = (info ? info + ' · ' : '') + status;
        } else {
            SEND_SHEET_CHIP.style.display = 'none';
        }
    }

    // Spinner « Chargement de l'aperçu… » tant que le média n'est pas affiché.
    function setSheetMediaLoading(on) {
        SEND_SHEET_LOADER.style.display = on ? 'flex' : 'none';
    }

    function reallyHidePhotoPreview() {
        // Libère les aperçus locaux (fichiers non encore téléversés).
        if (pendingVideo && pendingVideo.localUrl) {
            try { URL.revokeObjectURL(pendingVideo.localUrl); } catch (e) {}
        }
        if (pendingPhoto && pendingPhoto.localUrl) {
            try { URL.revokeObjectURL(pendingPhoto.localUrl); } catch (e) {}
        }
        pendingPhoto = null;
        pendingVideo = null;
        pendingCaption = null;
        SEND_SHEET_VIDEO.pause();
        SEND_SHEET_IMG.removeAttribute('src');
        SEND_SHEET_VIDEO.removeAttribute('src');
        SEND_SHEET_VIDEO.removeAttribute('poster');
        SEND_SHEET_CAPTION.value = '';
        setSheetMediaLoading(false);
        SEND_SHEET.style.display = 'none';
        SEND_SHEET.classList.remove('disc-send-sheet-show');
        resetSheetKeyboardLayout();
        PHOTO_INPUT.value = '';
        refreshSendBtn();
    }

    // Sortie de l'écran de prévisualisation.
    function hidePhotoPreview() {
        clearTimeout(mediaSheetHideTimer);
        if (mediaSheetExiting) {
            return;
        }
        if (document.fullscreenElement === SEND_SHEET) {
            // En plein écran, le navigateur a besoin d'un court délai pour
            // annuler le plein écran : masquer avant provoquerait un flash du
            // média. On attend l'évènement fullscreenchange (filet de sécurité).
            mediaSheetExiting = true;
            document.removeEventListener('fullscreenchange', mediaSheetOnExit);
            document.addEventListener('fullscreenchange', mediaSheetOnExit, { once: true });
            document.exitFullscreen().catch(() => {
                mediaSheetExiting = false;
                reallyHidePhotoPreview();
            });
            mediaSheetHideTimer = setTimeout(() => {
                if (mediaSheetExiting) {
                    mediaSheetExiting = false;
                    reallyHidePhotoPreview();
                }
            }, 500);
        } else {
            reallyHidePhotoPreview();
        }
    }

    function mediaSheetOnExit() {
        if (document.fullscreenElement) return; // le plein écran est passé à un autre élément
        clearTimeout(mediaSheetHideTimer);
        mediaSheetExiting = false;
        reallyHidePhotoPreview();
    }

    /* ---------- Clavier iOS : l'écran doit rester au-dessus du clavier ----------
       iOS ne redimensionne pas les éléments position:fixed quand le clavier
       s'ouvre (contrairement à Android) : on recale l'écran sur la zone
       réellement visible grâce au visualViewport. */
    let sheetKeyboardBound = false;
    function layoutSheetForKeyboard() {
        if (SEND_SHEET.style.display === 'none') return;
        const vv = window.visualViewport;
        if (!vv) return;
        SEND_SHEET.style.top = (vv.offsetTop || 0) + 'px';
        SEND_SHEET.style.height = vv.height + 'px';
    }
    function bindSheetKeyboardLayout() {
        if (!window.visualViewport || sheetKeyboardBound) return;
        sheetKeyboardBound = true;
        let raf = null;
        const apply = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(layoutSheetForKeyboard);
        };
        window.visualViewport.addEventListener('resize', apply);
        window.visualViewport.addEventListener('scroll', apply);
    }
    function resetSheetKeyboardLayout() {
        SEND_SHEET.style.top = '';
        SEND_SHEET.style.height = '';
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

    // Taille d'un fichier vidéo en Mo, format français (ex. « 37 Mo », « 2,4 Mo »).
    function formatVideoSize(bytes) {
        const mb = bytes / (1024 * 1024);
        if (!isFinite(mb) || mb <= 0) return '';
        if (mb >= 10) return Math.round(mb) + ' Mo';
        return (Math.round(mb * 10) / 10).toString().replace('.', ',') + ' Mo';
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
        SEND_BTN.disabled = !INPUT_EL.value.trim() && !pendingGif && !pendingPhoto && !pendingVideo && !isMicRecording();
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

    /* ---------- Envoi de photo / vidéo ---------- */

// Capture la première frame exploitable d'une vidéo chargée (metadata OK) et la
// renvoie en Blob JPEG. Un <video> jamais joué affiche volontiers une image noire
// au drawImage : on fait donc tourner la vidéo (muted) le temps que le décodeur
// présente de vrais frames, et on ignore les frames quasi noirs. Sur iOS il faut
// amorcer une lecture muted pour forcer le décodage. En cas d'échec complet
// (> ~2 s), on rend un frame quand même plutôt que de bloquer.
function grabVideoThumb(videoEl) {
    return new Promise((resolve) => {
        if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) {
            resolve(null);
            return;
        }
        const maxW = 640;
        const maxH = Math.max(1, Math.round(maxW * videoEl.videoHeight / videoEl.videoWidth));

        let settled = false;
        let tries = 0;
        const finish = (canvas) => {
            if (settled) return;
            settled = true;
            try { videoEl.pause(); } catch (e) { /* sans conséquence */ }
            if (!canvas) { resolve(null); return; }
            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
        };

        // Luminance moyenne du cadre : un décodage pas encore prêt donne un cadre
        // (quasi) noir, inutilisable comme miniature. Seuil volontairement bas
        // pour ne pas écarter les vidéos sombres légitimes.
        const luminance = (canvas) => {
            try {
                const ctx = canvas.getContext('2d');
                const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                let sum = 0;
                for (let i = 0; i < data.length; i += 40) {
                    sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                }
                return sum / (data.length / 40);
            } catch (e) {
                return 255; // canvas illisible → on garde quand même le frame
            }
        };

        const drawFrame = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = maxW;
                canvas.height = maxH;
                canvas.getContext('2d').drawImage(videoEl, 0, 0, maxW, maxH);
                return canvas;
            } catch (e) {
                return null;
            }
        };

        const tryCapture = () => {
            if (settled) return;
            const canvas = drawFrame();
            if (canvas && luminance(canvas) > 10) { finish(canvas); return; }
            if (++tries >= 8) { finish(canvas); return; }
            setTimeout(tryCapture, 120);
        };

        // On vise légèrement après le début pour éviter la première image noire.
        const target = Math.min(2, Math.max(0.1, (videoEl.duration || 2) * 0.2));
        videoEl.muted = true;
        videoEl.currentTime = target;
        videoEl.addEventListener('seeked', () => {
            videoEl.play().catch(() => {});
            setTimeout(tryCapture, 200);
        }, { once: true });
        // Filet de sécurité (iOS : un seek seul peut ne rien décoder)…
        setTimeout(() => {
            if (settled) return;
            videoEl.play().catch(() => {});
            setTimeout(tryCapture, 300);
        }, 900);
    });
}

    // Envoie la miniature (poster) au serveur pour la stocker et la joindre au
    // message vidéo. Sans conséquences en cas d'échec : la vidéo part quand même.
    async function uploadVideoPoster(blob, pending) {
        if (!blob) return;
        const fd = new FormData();
        fd.append('poster', blob, 'poster.jpg');
        const res = await fetch(VIDEO_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: fd,
        });
        if (res.ok && pending) {
            const data = await res.json();
            pending.posterPath = data.poster_path || null;
            pending.posterUrl = data.poster_url || null;
        }
    }

    CAMERA_BTN.addEventListener('click', () => {
        closeGifPanel();
        PHOTO_INPUT.accept = 'image/*,video/*';
        // Pas de capture : sur iOS, sans cet attribut le picker ouvre la
        // bibliothèque de photos/vidéos (avec capture, il force l'appareil photo).
        PHOTO_INPUT.removeAttribute('capture');
        PHOTO_INPUT.removeAttribute('multiple');
        PHOTO_INPUT.click();
    });
    PHOTO_INPUT.addEventListener('change', () => {
        const file = PHOTO_INPUT.files[0];
        if (!file) return;
        const isVideo = (file.type || '').indexOf('video/') === 0;
        const maxBytes = isVideo ? 100 * 1024 * 1024 : 10 * 1024 * 1024;
        if (file.size > maxBytes) {
            toast(isVideo ? 'Vidéo trop lourde (max 100 Mo).' : 'Image trop lourde (max 10 Mo).', 'error');
            PHOTO_INPUT.value = '';
            return;
        }
        // Aperçu INSTANTANÉ depuis le fichier local : l'écran s'ouvre tout de
        // suite, avant même la fin de l'envoi (indispensable pour les vidéos
        // lourdes). L'upload se fait en arrière-plan, puis on réutilise le chemin
        // serveur pour l'envoi du message.
        const localUrl = URL.createObjectURL(file);
        SEND_SHEET_IMG.style.display = 'none';
        SEND_SHEET_IMG.removeAttribute('src');
        SEND_SHEET_VIDEO.style.display = 'none';
        SEND_SHEET_VIDEO.removeAttribute('src');
        SEND_SHEET_VIDEO.removeAttribute('poster');
        setSheetMediaLoading(true);

        const upload = async (fd) => {
            const res = await fetch(isVideo ? VIDEO_URL : PHOTO_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || (isVideo ? 'Vidéo invalide.' : 'Photo invalide.'));
            }
            return res.json();
        };

        if (isVideo) {
            pendingPhoto = null;
            const v = {
                localUrl,
                path: null, url: null,
                w: 0, h: 0,
                sizeLabel: formatVideoSize(file.size),
                durationLabel: '',
                posterPath: null, posterUrl: null,
                uploading: true, posterPending: true,
            };
            pendingVideo = v;
            SEND_SHEET_VIDEO.style.display = 'block';
            SEND_SHEET_VIDEO.onloadedmetadata = async () => {
                if (pendingVideo !== v) return;
                // Les dimensions natives réservent la hauteur de la bulle sans
                // attendre le chargement de la vignette côté fil.
                v.w = SEND_SHEET_VIDEO.videoWidth || 0;
                v.h = SEND_SHEET_VIDEO.videoHeight || 0;
                v.durationLabel = formatAudioTime(SEND_SHEET_VIDEO.duration);
                // Miniature de la première frame (poster) : indispensable sur iOS,
                // où un <video> sans poster reste noir. En cas d'échec (décodage
                // impossible), on envoie quand même.
                setSheetMediaLoading(true);
                const blob = await grabVideoThumb(SEND_SHEET_VIDEO);
                if (pendingVideo !== v) return;
                if (blob !== null) {
                    SEND_SHEET_VIDEO.poster = URL.createObjectURL(blob);
                }
                setSheetMediaLoading(false);
                updateSheetControls();
                uploadVideoPoster(blob, v)
                    .then(() => { if (pendingVideo === v) { v.posterPending = false; updateSheetControls(); } })
                    .catch(() => { if (pendingVideo === v) { v.posterPending = false; updateSheetControls(); } });
            };
            SEND_SHEET_VIDEO.src = localUrl;
            openMediaSheet();
            const fd = new FormData();
            fd.append('video', file);
            upload(fd)
                .then((data) => {
                    if (pendingVideo !== v) return;
                    v.path = data.path;
                    v.url = data.url;
                    v.uploading = false;
                    updateSheetControls();
                })
                .catch((err) => {
                    toast(err && err.message ? err.message : 'Connexion perdue.', 'error');
                    if (pendingVideo === v) hidePhotoPreview();
                });
        } else {
            pendingVideo = null;
            const p = { localUrl, path: null, url: null, w: 0, h: 0, uploading: true };
            pendingPhoto = p;
            SEND_SHEET_IMG.style.display = 'block';
            SEND_SHEET_IMG.onload = () => {
                // On retient les dimensions natives : elles réservent la hauteur
                // de la bulle sans attendre le chargement côté fil.
                if (pendingPhoto !== p) return;
                p.w = SEND_SHEET_IMG.naturalWidth || 0;
                p.h = SEND_SHEET_IMG.naturalHeight || 0;
                setSheetMediaLoading(false);
            };
            SEND_SHEET_IMG.onerror = () => setSheetMediaLoading(false);
            SEND_SHEET_IMG.src = localUrl;
            openMediaSheet();
            const fd = new FormData();
            fd.append('photo', file);
            upload(fd)
                .then((data) => {
                    if (pendingPhoto !== p) return;
                    p.path = data.path;
                    p.url = data.url;
                    p.uploading = false;
                    updateSheetControls();
                })
                .catch((err) => {
                    toast(err && err.message ? err.message : 'Connexion perdue.', 'error');
                    if (pendingPhoto === p) hidePhotoPreview();
                });
        }
    });
    SEND_SHEET_BACK.addEventListener('click', () => {
        restoreCaptionToComposer();
        hidePhotoPreview();
    });
    SEND_SHEET_CAPTION.addEventListener('input', () => {
        pendingCaption = SEND_SHEET_CAPTION.value.trim();
    });
    SEND_SHEET_CAPTION.addEventListener('focus', layoutSheetForKeyboard);
    SEND_SHEET_CAPTION.addEventListener('blur', layoutSheetForKeyboard);
    SEND_SHEET_CAPTION.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!SEND_SHEET_SEND.disabled) sendMessage();
        }
    });
    SEND_SHEET_SEND.addEventListener('click', sendMessage);
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

    // Icônes d'appel (décoratives) : ouvre un modal "bientôt disponible".
    const CALL_MODAL = document.getElementById('disc-call-modal');
    const CALL_MODAL_TITLE = document.getElementById('disc-call-modal-title');
    function openCallModal(title) {
        CALL_MODAL_TITLE.textContent = title;
        CALL_MODAL.style.display = 'grid';
    }
    function closeCallModal() {
        CALL_MODAL.style.display = 'none';
    }
    document.getElementById('disc-call-voice').addEventListener('click', () => openCallModal('Appel vocal'));
    document.getElementById('disc-call-video').addEventListener('click', () => openCallModal('Appel vidéo'));
    CALL_MODAL.querySelectorAll('[data-call-close]').forEach((el) => el.addEventListener('click', closeCallModal));
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && CALL_MODAL.style.display !== 'none') closeCallModal();
    });

// "Pré-rendu" côté client : les messages injectés par le serveur dans
    // #disc-init-messages sont construits avec buildBubble (le MÊME rendu que le
    // fetch) pendant le parse, donc avant la première peinture. Aucun HTML de
    // bulle n'est écrit côté serveur : l'affichage ne peut pas différer.
    // Pour ne pas bloquer la première peinture (écran noir sur les longs
    // historiques), on construit de façon synchrone seulement LA FIN du fil
    // (les ~50 derniers messages, la partie visible en arrivant) ; les plus
    // anciens sont ensuite construits après la peinture, par lots (rAF), et
    // insérés AU-DESSUS. Sur le visible, rien ne bouge : la zone reste invisible
    // (visibility:hidden) seulement le temps de pré-afficher la fin du fil.
    // Les photos réservent leur hauteur (aspect-ratio/width) → le chargement ne
    // décale pas le fil : on révèle d'un coup sur la hauteur finale des derniers
    // messages, avant la première peinture → ouverture directe sur le dernier
    // message, sans défilement.
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
        // iOS : la barre d'URL se replie ~0,5 s après l'arrivée et redimensionne
        // le viewport, ce qui décollait le fil du bas APRÈS le collage initial
        // (on retombait quelques messages avant, puis le re-collage tardif créait
        // un saut visible). Pendant les ~1,5 premières secondes, on maintient le
        // fil en bas à chaque frame tant que rien ne l'a décroché : la correction
        // est invisible. Dès que l'utilisateur fait défiler vers le haut (au-delà
        // de la marge de tolérance), on libère tout et on ne recolle plus.
        let initialScrollAway = false;
        const watchInitialScroll = () => {
            if (!wasAtBottom()) initialScrollAway = true;
        };
        MESSAGES_EL.addEventListener('scroll', watchInitialScroll, { passive: true });
        const settleStart = Date.now();
        const settleEnd = () => {
            initialScrollAway = true;
            MESSAGES_EL.removeEventListener('scroll', watchInitialScroll);
        };
        const pinDuringSettle = () => {
            if (initialScrollAway || Date.now() - settleStart > 1500) {
                settleEnd();
                return;
            }
            if (!wasAtBottom()) MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
            requestAnimationFrame(pinDuringSettle);
        };
        requestAnimationFrame(pinDuringSettle);
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
        }
        list = list.map(localizeMsg);
        if (list.length === 0) {
            revealDisc();
            return;
        }

        // On sépare : la fin du fil (pré-affiche synchrone) et les plus anciens
        // (construits après la peinture, sans bloquer).
        const TAIL_LEN = 50;
        const splitAt = Math.max(0, list.length - TAIL_LEN);
        const older = list.slice(0, splitAt);
        const tail = list.slice(splitAt);

        // Point d'insertion invisible placé à la fin : la fin du fil se rend en
        // dessous de lui, les plus anciens s'empileront juste au-dessus →
        // ordre chronologique conservé quelle que soit l'ordre des passes.
        bootAnchor = document.createElement('i');
        bootAnchor.className = 'disc-boot-anchor';
        bootAnchor.style.display = 'none';
        bootAnchor.dataset.id = tail[0].id;
        bootAnchor.dataset.bootDate = tail[0].date || '';
        MESSAGES_EL.appendChild(bootAnchor);

        // Le premier message du pré-rendu succède à un historique non construit :
        // on ne pose AUCUN séparateur devant lui (le joint est géré quand les
        // plus anciens arrivent). Le très premier message de la conversation
        // garde son séparateur (cas où tout tient dans la fin pré-affichée).
        if (older.length > 0 && tail[0]) {
            MESSAGES_EL.dataset.bootNoSep = '1';
        }
        for (const m of tail) buildBubble(m);
        // Tous les médias réservent leur hauteur (photos via dimensions natives,
        // GIF via carré 1/1) : le chargement ne décale pas le fil, on révèle
        // immédiatement sans écran noir pour la partie visible.
        revealDisc();

        // Les messages plus anciens se construisent après la première peinture,
        // par lots (rAF), sans rien bloquer (le visible reste en bas).
        if (older.length > 0) {
            prefillDone = false;
            prefillOlder(older);
        }
    }

    // Colle le séparateur de date manquant au joint : le premier message du
    // pré-rendu n'en a pas (hérité d'un historique non encore construit) ; quand
    // les plus anciens arrivent et changent de jour, on le pose a posteriori.
    function joinSep(anchor) {
        if (!anchor || !anchor.dataset.bootDate) return;
        const prev = anchor.previousElementSibling;
        if (!prev || prev.classList.contains('disc-bubble-wrap') === false) return;
        if ((prev.dataset.date || '') === anchor.dataset.bootDate) return;
        const sep = document.createElement('div');
        sep.className = 'disc-date-sep';
        sep.innerHTML = '<span>' + escHtml(formatDate(anchor.dataset.bootDate)) + '</span>';
        MESSAGES_EL.insertBefore(sep, anchor);
    }

    function prefillOlder(older) {
        // On construit l'historique restant par petits lots sur plusieurs frames :
        // la page est déjà affichée et reste interactive pendant ce temps. Les
        // bulles s'insèrent avant bootAnchor (au-dessus du fil), dans l'ordre.
        const CHUNK = 20;
        let i = 0;
        const step = () => {
            const h0 = MESSAGES_EL.scrollHeight;
            const end = Math.min(i + CHUNK, older.length);
            for (; i < end; i++) buildBubble(older[i]);
            // Compense l'insertion du lot : si l'utilisateur est en bas, on reste
            // collé ; sinon on garde la portion affichée stable (le contenu ajouté
            // au-dessus ne le fait pas sauter).
            const added = MESSAGES_EL.scrollHeight - h0;
            if (wasAtBottom()) {
                MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
            } else if (added > 0) {
                MESSAGES_EL.scrollTop += added;
            }
            if (i < older.length) {
                requestAnimationFrame(step);
            } else {
                joinSep(bootAnchor);
                prefillDone = true;
                if (wasAtBottom()) MESSAGES_EL.scrollTop = MESSAGES_EL.scrollHeight;
            }
        };
        requestAnimationFrame(step);
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
