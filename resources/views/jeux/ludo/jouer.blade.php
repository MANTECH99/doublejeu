@extends('layouts.app')

@section('title', 'Ludo à deux')

@section('content')
    <div class="fadeIn">
        <div class="center mb8">
            <span class="badge neutre" style="font-size:14px; padding:7px 14px" id="ludo-status">Chargement…</span>
        </div>

        <div class="ludo-tete">
            <div class="ludo-joueur" id="ludo-j1">—</div>
            <div class="ludo-de" id="ludo-de">
                <div id="de-valeur" class="de-valeur"><span id="de-emoji">🎲</span><div id="de-grille" class="de-grille"><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span><span class="de-pip"></span></div></div>
            </div>
            <div class="ludo-joueur" id="ludo-j2">—</div>
        </div>

        <div class="center mb8" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
            <button id="btn-lancer" class="btn btn-sm btn-primary" style="display:none" onclick="lancer()">🎲 Lancer le dé</button>
            <button id="btn-pion-mode" class="btn btn-sm" onclick="changerPionMode()">Pions : Pastille</button>
        </div>

        @php
            $path = [
                [6, 0], [6, 1], [6, 2], [6, 3], [6, 4], [6, 5], [5, 6], [4, 6], [3, 6], [2, 6], [1, 6], [0, 6],
                [0, 7], [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [6, 9], [6, 10], [6, 11], [6, 12], [6, 13],
                [6, 14], [7, 14], [8, 14], [8, 13], [8, 12], [8, 11], [8, 10], [8, 9], [9, 8], [10, 8], [11, 8],
                [12, 8], [13, 8], [14, 8], [14, 7], [14, 6], [13, 6], [12, 6], [11, 6], [10, 6], [9, 6], [8, 5],
                [8, 4], [8, 3], [8, 2], [8, 1], [8, 0], [7, 0],
            ];
            $bases = [
                'rouge' => [[2, 2], [2, 4], [4, 2], [4, 4]],
                'bleue' => [[10, 10], [10, 12], [12, 10], [12, 12]],
            ];
        @endphp

        <div class="ludo-board">
            <div class="couche corner-corner corner-rouge" style="grid-row:1/7; grid-column:1/7"></div>
            <div class="couche corner-corner corner-bleue" style="grid-row:10/16; grid-column:10/16"></div>
            <div class="couche nom-zone nom-zone-haut" style="grid-row:1/7; grid-column:1/7"><b class="corner-nom" id="nom-rouge"></b></div>
            <div class="couche nom-zone" style="grid-row:10/16; grid-column:10/16"><b class="corner-nom" id="nom-bleue"></b></div>
            <div class="couche corner-deco" style="grid-row:1/7; grid-column:10/16">
                <div class="deco-souligne">
                    <span class="deco-emoji">💘</span>
                    <span class="deco-texte">L'amour gagne à tous les coups</span>
                </div>
            </div>
            <div class="couche corner-deco" style="grid-row:10/16; grid-column:1/7">
                <div class="deco-souligne">
                    <span class="deco-emoji">💞</span>
                    <span class="deco-texte">Deux cœurs, un seul qui bat</span>
                </div>
            </div>
            <div class="couche centre" style="grid-row:7/10; grid-column:7/10">
                <div id="finish"></div>
            </div>

            @foreach ($path as $i => [$r, $c])
                <div class="cell path{{ $i === 1 ? ' porte-rouge' : ($i === 9 ? ' etoile-rouge' : ($i === 22 ? ' etoile-neutre' : ($i === 27 ? ' porte-bleue' : ($i === 35 ? ' etoile-bleue' : ($i === 48 ? ' etoile-neutre' : ''))))) }}"
                     id="cell-{{ $r }}-{{ $c }}"
                     style="grid-row:{{ $r + 1 }}; grid-column:{{ $c + 1 }}"></div>
            @endforeach

            @for ($h = 0; $h < 5; $h++)
                <div class="cell lane-rouge" id="lane-rouge-{{ $h }}" style="grid-row:8; grid-column:{{ 2 + $h }}"></div>
            @endfor
            @for ($h = 0; $h < 5; $h++)
                <div class="cell lane-bleue" id="lane-bleue-{{ $h }}" style="grid-row:8; grid-column:{{ 14 - $h }}"></div>
            @endfor

            @foreach ($bases as $couleur => $slots)
                @foreach ($slots as $k => [$r, $c])
                    <div class="cell base-{{ $couleur }}" id="base-{{ $couleur }}-{{ $k }}"
                         style="grid-row:{{ $r + 1 }}; grid-column:{{ $c + 1 }}"></div>
                @endforeach
            @endforeach
        </div>

        <div id="ludo-fin" style="display:none" class="card mt16">
            <div class="center">
                <div style="font-size:34px">🏆</div>
                <h2 style="font-size:18px" id="fin-titre"></h2>
                <p class="muted tiny" id="fin-sous-titre"></p>
                <a href="{{ route('ludo.index') }}" class="btn btn-sm btn-primary mt8">Nouvelle partie / Retour</a>
            </div>
        </div>

        <p class="tiny muted center mt8">💡 Sors avec un 6, renvoie le pion adverse au départ en atterrissant sur sa case, un 6 te fait rejouer.</p>

        <div class="center" style="margin-top:96px">
            <button id="btn-abandonner" class="btn btn-sm btn-danger-outline" style="display:none" onclick="abandonner()">Abandonner</button>
        </div>
    </div>
@endsection

@push('head')
    <style>
        .ludo-tete { display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:8px; }
        .ludo-joueur {
            font-size:13px; font-weight:700; text-align:center; max-width:120px;
            background:var(--card); border:1px solid var(--border); border-radius:12px; padding:5px 10px;
        }
        .ludo-joueur .pdot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; vertical-align:middle; }
        .pdot.rouge { background:#e63946; }
        .pdot.bleue { background:#3498db; }
        .ludo-joueur .perc { color:var(--success); font-size:11px; }
        .ludo-de {
            background:#fffef8; border:2px solid #d8d0c0; border-radius:14px;
            width:58px; height:58px; display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 14px rgba(0,0,0,.25), inset 0 0 0 2px rgba(255,255,255,.6);
        }
        #de-valeur { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
        #de-emoji { font-size:30px; line-height:1; color:var(--primary-2); }
        #de-grille.de-grid {
            display:grid; grid-template-columns:repeat(3,1fr); grid-template-rows:repeat(3,1fr);
            width:100%; height:100%; padding:9px; box-sizing:border-box; gap:2px;
        }
        .de-pip { border-radius:50%; }
        .de-pip.on {
            background:radial-gradient(circle at 35% 30%, #6d6a63, #2f2c26 70%, #191612);
            box-shadow:inset -1px -2px 2px rgba(0,0,0,.5), 0 1px 1px rgba(255,255,255,.35);
        }
        #ludo-de.shake { animation:ldShake .45s; }
        @keyframes ldShake { 0%,100%{transform:rotate(0)} 25%{transform:rotate(-14deg)} 50%{transform:rotate(10deg)} 75%{transform:rotate(-7deg)} }
        #ludo-de.rollem { animation:ldRoulle 1s cubic-bezier(.4,.1,.3,1); }
        @keyframes ldRoulle {
            0%{ transform:rotate(0deg) scale(1); }
            30%{ transform:rotate(120deg) scale(1.12); }
            55%{ transform:rotate(-60deg) scale(.96); }
            80%{ transform:rotate(40deg) scale(1.04); }
            100%{ transform:rotate(0deg) scale(1); }
        }

        .ludo-board {
            display:grid; grid-template-columns:repeat(15,1fr); grid-template-rows:repeat(15,1fr);
            gap:0; position:relative; aspect-ratio:1; max-width:520px; margin:0 auto;
            background:#f2efe8; border:3px solid #d8d3c5; border-radius:16px; overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.35);
        }
        .couche { z-index:0; pointer-events:none; }
        .nom-zone { z-index:4; display:flex; align-items:flex-end; justify-content:center; padding:0 8px 4px; text-align:center; }
        .nom-zone-haut { align-items:flex-start; padding:6px 8px 0; }
        .corner-nom {
            font-family:var(--font-sans, inherit);
            background:rgba(255,255,255,.72); border-radius:12px; padding:3px 12px;
            font-size:12px; font-weight:800; color:#5a2d33;
            text-align:center; line-height:1.25;
            box-shadow:0 2px 5px rgba(0,0,0,.16);
        }
        .corner-rouge { background:linear-gradient(160deg, #f9d7d9, #f2b7bb); }
        .corner-bleue { background:linear-gradient(160deg, #d7e8f7, #a9cdf0); }
        .corner-deco { display:flex; align-items:center; justify-content:center; background:linear-gradient(160deg, #f8dfab 0%, #efc484 100%); }
        .deco-souligne {
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            text-align:center; gap:5px; padding:10px; width:100%; height:100%;
            box-sizing:border-box; line-height:1.25;
        }
        .deco-emoji { font-size:34px; line-height:1; filter:drop-shadow(0 2px 3px rgba(0,0,0,.2)); }
        .deco-texte { font-size:11px; font-weight:800; color:#7c5317; text-align:center; max-width:94%; line-height:1.3; }
        .centre {
            background:#f7f3ea;
            display:flex; align-items:center; justify-content:center;
        }
        #finish {
            position:relative; width:100%; height:100%; border-radius:10px;
            background:radial-gradient(circle at 50% 40%, #ffffff, #fdf3dc 85%);
            box-shadow: inset 0 0 0 3px #f0c269, 0 2px 6px rgba(0,0,0,.14);
            display:flex; align-items:center; justify-content:center;
            font-size:32px; line-height:1; z-index:1;
        }
        #finish::before {
            content:'💞'; position:absolute; inset:0;
            display:flex; align-items:center; justify-content:center;
            font-size:34px; line-height:1;
        }

        .cell { position:relative; z-index:2; box-sizing:border-box; display:flex; align-items:center; justify-content:center; }
        .cell.path { background:#fff; box-shadow:inset 0 0 0 1px #e2dfd5; }
        .cell.path.porte-rouge { background:#fde2e4; box-shadow:inset 0 0 0 2px #e63946, 0 0 6px rgba(230,57,70,.35); }
        .cell.path.porte-bleue { background:#d6e9f9; box-shadow:inset 0 0 0 2px #3498db, 0 0 6px rgba(52,152,219,.35); }
        .cell.path.porte-rouge::before, .cell.path.porte-bleue::before,
        .cell.path.etoile-rouge::before, .cell.path.etoile-bleue::before,
        .cell.path.etoile-neutre::before {
            content:'★'; font-size:16px; line-height:1; position:absolute; inset:0;
            display:flex; align-items:center; justify-content:center;
        }
        .cell.path.porte-rouge::before, .cell.path.etoile-rouge::before { color:#e63946; }
        .cell.path.porte-bleue::before, .cell.path.etoile-bleue::before { color:#3498db; }
        .cell.path.etoile-neutre::before { color:#c29f2e; }
        .cell.lane-rouge { background:#f1b8bd; box-shadow:inset 0 0 0 1px #e3a3a9; }
        .cell.lane-bleue { background:#a9cdf0; box-shadow:inset 0 0 0 1px #8bb4de; }
        .cell.base-rouge { background:radial-gradient(circle at 35% 35%, #ff9ba1, #e63946); border-radius:10px; box-shadow:inset 0 0 0 2px rgba(255,255,255,.55); }
        .cell.base-bleue { background:radial-gradient(circle at 35% 35%, #83c9f5, #3498db); border-radius:10px; box-shadow:inset 0 0 0 2px rgba(255,255,255,.55); }

        .pion {
            position:absolute; border-radius:50%; z-index:3; cursor:default;
            transition:top .25s ease, left .25s ease;
            box-shadow: inset -3px -5px 7px rgba(0,0,0,.25), inset 3px 4px 6px rgba(255,255,255,.25), 0 3px 6px rgba(0,0,0,.42);
        }
        .pion::before {
            content:''; position:absolute; width:58%; height:58%; border-radius:50%;
            background:radial-gradient(circle at 35% 30%, rgba(255,255,255,.95), rgba(255,255,255,0) 62%);
        }
        .pion-rouge { background:radial-gradient(circle at 50% 38%, #ff8f8f 0%, #e63946 55%, #8f1420 100%); }
        .pion-bleue { background:radial-gradient(circle at 50% 38%, #8fd0f8 0%, #2c80c4 55%, #123f66 100%); }
        .pion.legal { cursor:pointer; animation:pionPulse .8s ease-in-out infinite; }
        .pion.pion-done { background:rgba(255,255,255,.55); box-shadow: inset 0 0 0 2px currentColor, 0 2px 4px rgba(0,0,0,.3); }
        .pion.pion-done.pion-rouge { color:#e63946; }
        .pion.pion-done.pion-bleue { color:#3498db; }
        @keyframes pionPulse {
            0%,100% { box-shadow:0 0 0 0 transparent, 0 2px 5px rgba(0,0,0,.45); transform:scale(1); filter:brightness(1); }
            50% { box-shadow:0 0 0 8px rgba(255,255,255,0), 0 0 18px 4px rgba(255,215,0,.95), 0 2px 5px rgba(0,0,0,.3); transform:scale(1.18); filter:brightness(1.45); }
        }
        .pion[data-mode='diamant'].legal { animation:pionPulseDia .8s ease-in-out infinite; }
        @keyframes pionPulseDia {
            0%,100% { box-shadow:0 0 0 0 transparent, 0 2px 5px rgba(0,0,0,.45); transform:rotate(45deg) scale(1); filter:brightness(1); }
            50% { box-shadow:0 0 0 8px rgba(255,255,255,0), 0 0 18px 4px rgba(255,215,0,.95), 0 2px 5px rgba(0,0,0,.3); transform:rotate(45deg) scale(1.18); filter:brightness(1.45); }
        }

        .pion[data-mode='boule'] { border-radius:50%; }
        .pion[data-mode='boule']::before {
            content:''; position:absolute; width:58%; height:58%; border-radius:50%;
            background:radial-gradient(circle at 35% 30%, rgba(255,255,255,.95), rgba(255,255,255,0) 62%);
        }

        .pion[data-mode='casquette'] {
            border-radius:16% 16% 38% 38%;
            box-shadow: inset -3px -5px 8px rgba(0,0,0,.3), 0 4px 7px rgba(0,0,0,.42);
        }
        .pion[data-mode='casquette']::before {
            content:''; position:absolute; top:6%; left:16%; width:68%; height:46%; border-radius:50%;
            background:radial-gradient(circle at 35% 30%, rgba(255,255,255,.55), rgba(255,255,255,0) 60%);
        }
        .pion[data-mode='casquette']::after {
            content:''; position:absolute; bottom:10%; left:4%; width:92%; height:22%; border-radius:6px;
            background:rgba(0,0,0,.18);
        }

        .pion[data-mode='pastille'] { border-radius:50%; }
        .pion[data-mode='pastille']::before {
            content:''; position:absolute; inset:8%; border-radius:50%;
            background:radial-gradient(circle at 40% 35%, rgba(255,255,255,.75), rgba(255,255,255,.12) 70%);
            box-shadow: inset 0 0 0 2px rgba(255,255,255,.5);
        }

        .pion[data-mode='coeur'] { border-radius:50%; overflow:hidden; }
        .pion[data-mode='coeur']::before {
            content:''; position:absolute; inset:0; background:radial-gradient(circle at 30% 25%, rgba(255,255,255,.65), rgba(255,255,255,0) 55%);
        }

        .pion[data-mode='diamant'] { border-radius:0; transform:rotate(45deg); }
        .pion[data-mode='diamant']::before {
            content:''; position:absolute; inset:14%; transform:rotate(-45deg); border-radius:50%;
            background:radial-gradient(circle at 40% 35%, rgba(255,255,255,.7), rgba(255,255,255,0) 65%);
        }
    </style>
@endpush

@push('scripts')
    <script>
        const ludoUrl = @json(url('jeux/ludo/'.$partie->id));
        const moiId = @json(Auth::id());
        const PATH = @json($path);
        const tokens = {}; // pion id → élément DOM
        let dePrecedent = null;
        const PIPS_DE = {
            1: [5],
            2: [3, 7],
            3: [3, 5, 7],
            4: [1, 3, 7, 9],
            5: [1, 3, 5, 7, 9],
            6: [1, 3, 4, 6, 7, 9],
        };
        const MODES_PIONS = ['pastille', 'boule', 'casquette', 'coeur', 'diamant'];
        let modePion = localStorage.getItem('ludo-mode-pion') || MODES_PIONS[0];

        function modePionNom(m) {
            return {boule: 'Boule', casquette: 'Casquette', pastille: 'Pastille', coeur: 'Cœur', diamant: 'Diamant'}[m] || m;
        }

        function changerPionMode() {
            const i = MODES_PIONS.indexOf(modePion);
            modePion = MODES_PIONS[(i + 1) % MODES_PIONS.length];
            localStorage.setItem('ludo-mode-pion', modePion);
            const btn = document.getElementById('btn-pion-mode');
            if (btn) btn.textContent = 'Pions : ' + modePionNom(modePion);
            Object.values(tokens).forEach(el => { el.dataset.mode = modePion; });
            if (dernierEtat) dessinerPions(dernierEtat);
        }
        let dernierEtat = null;
        let positionsConnues = {};
        let animationsJusqua = {};
        let optimistiques = {};

        function cibleDe(p) {
            const conteneurId = p.position === -1 ? 'base-' + p.couleur + '-' + p.numero
                : (p.position <= 51 ? 'cell-' + PATH[p.case][0] + '-' + PATH[p.case][1]
                    : (p.position >= 57 ? 'finish' : 'lane-' + p.couleur + '-' + (p.position - 52)));
            return document.getElementById(conteneurId);
        }

        function positionnerPion(el, p, position) {
            position = position === undefined ? p.position : position;
            if (position >= 57) {
                const ligne = p.couleur === 'rouge' ? 0 : 1;
                const taille = 20;
                const gauche = 4 + (p.numero % 4) * 25;
                const haut = 6 + ligne * 42;
                el.style.left = gauche + '%';
                el.style.top = haut + '%';
                el.style.width = taille + '%';
                el.style.height = taille + '%';
                return;
            }
            const grand = modePion === 'diamant' || modePion === 'pastille';
            const taille = grand ? 60 : 50;
            const off = (100 - taille) / 2;
            el.style.left = off + '%';
            el.style.top = off + '%';
            el.style.width = taille + '%';
            el.style.height = taille + '%';
        }

        function conteneurPosition(p, position) {
            if (position === -1) {
                return document.getElementById('base-' + p.couleur + '-' + p.numero);
            }
            if (position <= 51) {
                const c = ((p.couleur === 'bleue' ? 26 : 0) + position) % 52;
                return document.getElementById('cell-' + PATH[c][0] + '-' + PATH[c][1]);
            }
            if (position >= 57) {
                return document.getElementById('finish');
            }
            return document.getElementById('lane-' + p.couleur + '-' + (position - 52));
        }

        function animerDeplacement(el, ancienRect) {
            const nouveauRect = el.getBoundingClientRect();
            const dx = ancienRect.left - nouveauRect.left;
            const dy = ancienRect.top - nouveauRect.top;
            if (!dx && !dy) return;
            const rot = el.dataset.mode === 'diamant' ? ' rotate(45deg)' : '';
            el.style.transition = 'none';
            el.style.transform = 'translate(' + dx + 'px,' + dy + 'px)' + rot;
            void el.offsetWidth;
            el.style.transition = 'transform .35s ease-in-out';
            el.style.transform = 'translate(0,0)' + rot;
            setTimeout(() => {
                el.style.transition = '';
                el.style.transform = '';
            }, 380);
        }

        function eparpiller(conteneur) {
            if (!conteneur || conteneur.id === 'finish') return;
            const pions = Array.from(conteneur.querySelectorAll('.pion'));
            if (pions.length <= 1) return;
            const taille = 34;
            const n = pions.length;
            pions.forEach((el, i) => {
                const gauche = n === 1 ? (100 - taille) / 2 : i * ((100 - taille) / (n - 1));
                el.style.width = taille + '%';
                el.style.height = taille + '%';
                el.style.left = gauche + '%';
                el.style.top = ((100 - taille) / 2) + '%';
            });
        }

        function lancerPasAPas(el, p, avant) {
            const etapes = [];
            for (let pos = avant + 1; pos <= p.position; pos++) etapes.push(pos);
            if (!etapes.length) return;
            const duree = 400;
            etapes.forEach((pos, i) => {
                setTimeout(() => {
                    const cont = conteneurPosition(p, pos);
                    if (!cont) return;
                    positionnerPion(el, p, pos);
                    el.classList.toggle('pion-done', pos >= 57);
                    el.dataset.mode = modePion;
                    if (el.parentNode !== cont) cont.appendChild(el);
                    if (i === etapes.length - 1) {
                        eparpiller(cont);
                        delete animationsJusqua[p.id];
                        delete optimistiques[p.id];
                        positionsConnues[p.id] = p.position;
                    }
                }, i * duree);
            });
        }

        function dessinerPions(state) {
            const animations = [];
            state.pions.forEach(p => {
                const legal = state.legal && state.legal.includes(p.id);
                const conteneur = cibleDe(p);
                if (!conteneur) return;

                let el = tokens[p.id];
                if (!el) {
                    el = document.createElement('div');
                    el.className = 'pion pion-' + p.couleur;
                    el.dataset.id = p.id;
                    el.title = 'Pion ' + (p.numero + 1);
                    el.onclick = () => bouger(p.id);
                    tokens[p.id] = el;
                }
                const avant = positionsConnues[p.id];
                if (optimistiques[p.id] && p.position < avant) {
                    return;
                }
                if (optimistiques[p.id] && p.position === avant) {
                    delete optimistiques[p.id];
                }
                const pasAPas = avant !== undefined && p.position > avant && avant >= 0 && p.position <= 57;
                if (pasAPas) {
                    positionsConnues[p.id] = p.position;
                    animationsJusqua[p.id] = Date.now() + (p.position - avant) * 400 + 80;
                    lancerPasAPas(el, p, avant);
                } else if (animationsJusqua[p.id] && Date.now() < animationsJusqua[p.id]) {
                    return;
                } else {
                    delete animationsJusqua[p.id];
                    if (el.parentNode && el.parentNode !== conteneur) {
                        animations.push({ el, ancienRect: el.getBoundingClientRect() });
                    }
                    el.dataset.mode = modePion;
                    el.classList.toggle('legal', legal);
                    el.classList.toggle('pion-done', p.position >= 57);
                    positionnerPion(el, p);
                    if (el.parentNode !== conteneur) conteneur.appendChild(el);
                    positionsConnues[p.id] = p.position;
                }
            });

            const vus = new Set();
            state.pions.forEach(p => {
                const conteneur = cibleDe(p);
                if (!conteneur || conteneur.id === 'finish' || vus.has(conteneur)) return;
                vus.add(conteneur);
                eparpiller(conteneur);
            });

            animations.forEach(a => animerDeplacement(a.el, a.ancienRect));
        }

        function render(state) {
            dernierEtat = state;
            dessinerPions(state);
            ['j1', 'j2'].forEach(cle => {
                const j = state.joueurs[cle];
                const el = document.getElementById('ludo-' + cle);
                const arrive = state.pions.filter(p => p.joueur_id === j.id && p.position >= 57).length;
                el.innerHTML = '<span class="pdot ' + j.couleur + '"></span>' + esc(j.name) + ' <span class="perc">' + arrive + '/4</span>';
            });

            document.getElementById('nom-rouge').textContent = state.joueurs.j1.name;
            document.getElementById('nom-bleue').textContent = state.joueurs.j2.name;

            const deBox = document.getElementById('ludo-de');
            const emojiEl = document.getElementById('de-emoji');
            const grilleEl = document.getElementById('de-grille');
            const valeurAffiche = state.dernier_de !== null ? state.dernier_de : (state.de_passe !== null ? state.de_passe : '🎲');
            if (valeurAffiche !== dePrecedent) {
                deBox.classList.remove('shake', 'rollem');
                void deBox.offsetWidth;
                deBox.classList.add(valeurAffiche === '🎲' ? 'shake' : 'rollem');
            }
            dePrecedent = valeurAffiche;
            if (typeof valeurAffiche === 'number' && valeurAffiche >= 1 && valeurAffiche <= 6) {
                emojiEl.style.display = 'none';
                grilleEl.classList.add('de-grid');
                const pips = PIPS_DE[valeurAffiche];
                const cases = grilleEl.querySelectorAll('.de-pip');
                cases.forEach((c, i) => c.classList.toggle('on', pips.includes(i + 1)));
            } else {
                emojiEl.style.display = '';
                grilleEl.classList.remove('de-grid');
            }

            const status = document.getElementById('ludo-status');
            const btnLancer = document.getElementById('btn-lancer');
            const btnAbandon = document.getElementById('btn-abandonner');
            const fin = document.getElementById('ludo-fin');

            if (state.statut === 'terminee' && state.vainqueur_id) {
                const v = objectValues(state.joueurs).find(j => j.id === state.vainqueur_id);
                status.className = 'badge succes';
                status.textContent = '🎉 Partie terminée';
                btnLancer.style.display = 'none';
                btnAbandon.style.display = 'none';
                fin.style.display = 'block';
                document.getElementById('fin-titre').textContent = 'Victoire de ' + v.name + ' !';
                const perdant = objectValues(state.joueurs).find(j => j.id !== v.id);
                document.getElementById('fin-sous-titre').textContent =
                    (v.id === moiId ? 'Tu gagnes +25 points 🎁' : perdant.name + ' gagne +25 points… à toi de prendre la revanche !');
                return;
            }

            fin.style.display = 'none';
            btnAbandon.style.display = state.statut === 'en_cours' ? 'inline-block' : 'none';

            if (state.statut === 'terminee') {
                status.className = 'badge neutre';
                status.textContent = 'Partie terminée';
                btnLancer.style.display = 'none';
                return;
            }

            const aMoi = state.tour_id === moiId;
            btnLancer.style.display = (aMoi && state.dernier_de === null) ? 'inline-block' : 'none';
            const passeInfo = state.de_passe !== null
                ? (objectValues(state.joueurs).find(j => j.id !== state.tour_id)?.name ?? 'Le/la partenaire') + ' a tiré ' + state.de_passe + ' : aucun coup possible.'
                : null;

            if (passeInfo && aMoi && state.dernier_de === null) {
                status.className = 'badge neutre';
                status.textContent = '🎲 ' + passeInfo + ' C\'est à toi : lance le dé !';
            } else if (aMoi && state.dernier_de === null) {
                status.className = 'badge neutre';
                status.textContent = '🎲 C\'est à toi : lance le dé !';
            } else if (aMoi) {
                status.className = 'badge neutre';
                status.textContent = state.dernier_de === 6
                    ? '6 ! Déplace un pion, tu rejoues ensuite.'
                    : 'Déplace un pion (' + state.dernier_de + ' cases).';
            } else if (passeInfo) {
                status.className = 'badge neutre';
                status.textContent = '⏳ ' + passeInfo + ' Tour passé.';
            } else {
                const opp = objectValues(state.joueurs).find(j => j.id === state.tour_id);
                status.className = 'badge neutre';
                status.textContent = '⏳ ' + (opp ? opp.name : 'Le/la partenaire') + ' joue…';
            }
        }

        function objectValues(o) { return Object.keys(o).map(k => o[k]); }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        let deAudioCtx = null;
        function unlockDeAudio() {
            if (deAudioCtx) return;
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            try {
                deAudioCtx = new AC();
                deAudioCtx.resume().catch(() => {});
            } catch (e) {
                deAudioCtx = null;
            }
        }
        ['pointerdown', 'touchstart', 'keydown'].forEach((ev) =>
            document.addEventListener(ev, () => unlockDeAudio(), { once: true, passive: true })
        );
        function toctocDe(t, freq, vol, durMs, type) {
            if (!deAudioCtx) return;
            const osc = deAudioCtx.createOscillator();
            const gain = deAudioCtx.createGain();
            const d = durMs / 1000;
            osc.type = type;
            osc.frequency.setValueAtTime(freq, t);
            osc.frequency.exponentialRampToValueAtTime(freq * 0.55, t + d);
            gain.gain.setValueAtTime(vol, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + d);
            osc.connect(gain);
            gain.connect(deAudioCtx.destination);
            osc.start(t);
            osc.stop(t + d);
        }
        function playDeSound() {
            if (!deAudioCtx) unlockDeAudio();
            if (!deAudioCtx) return;
            try {
                if (deAudioCtx.state === 'suspended') deAudioCtx.resume().catch(() => {});
                const base = deAudioCtx.currentTime + 0.02;
                const sequ = [0, 0.09, 0.17, 0.24];
                sequ.forEach((off, i) => {
                    toctocDe(base + off, 320 + (i % 3) * 60, 0.38, 30, 'triangle');
                });
                toctocDe(base + 0.27, 220, 0.5, 75, 'sine');
            } catch (e) {
                // silence
            }
        }
        function noteWin(t, freq, durMs, vol) {
            if (!deAudioCtx) return;
            const osc = deAudioCtx.createOscillator();
            const gain = deAudioCtx.createGain();
            const d = durMs / 1000;
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, t);
            gain.gain.setValueAtTime(vol, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + d);
            osc.connect(gain);
            gain.connect(deAudioCtx.destination);
            osc.start(t);
            osc.stop(t + d);
        }
        function playWinSound() {
            if (!deAudioCtx) unlockDeAudio();
            if (!deAudioCtx) return;
            try {
                if (deAudioCtx.state === 'suspended') deAudioCtx.resume().catch(() => {});
                const base = deAudioCtx.currentTime + 0.03;
                const notes = [523.25, 659.25, 783.99, 1046.5];
                notes.forEach((freq, i) => {
                    noteWin(base + i * 0.12, freq, 180, 0.3);
                });
                noteWin(base + 0.50, 1318.5, 380, 0.38);
            } catch (e) {
                // silence
            }
        }
        function playEatSound() {
            if (!deAudioCtx) unlockDeAudio();
            if (!deAudioCtx) return;
            try {
                if (deAudioCtx.state === 'suspended') deAudioCtx.resume().catch(() => {});
                const base = deAudioCtx.currentTime + 0.02;
                const notes = [659.25, 493.88, 329.63];
                notes.forEach((freq, i) => {
                    noteWin(base + i * 0.1, freq, 150, 0.3);
                });
                noteWin(base + 0.33, 261.63, 320, 0.3);
            } catch (e) {
                // silence
            }
        }

        async function lancer() {
            playDeSound();
            const res = await api(ludoUrl + '/lancer', { method: 'POST' });
            if (!res.ok || !res.data) return;
            if (res.data.passe) {
                toast('Aucun coup possible avec un ' + res.data.de + ' : tour passé.', 'info');
            }
            await refresh();
        }

        async function bouger(id) {
            const de = dernierEtat && dernierEtat.dernier_de;
            const pion = dernierEtat && dernierEtat.pions.find(p => p.id === id);
            const el = tokens[id];

            if (de && pion && el && dernierEtat.legal && dernierEtat.legal.includes(id)) {
                const depart = pion.position;
                const arrivee = depart === -1 ? 1 : Math.min(depart + de, 57);
                if (arrivee !== depart) {
                    optimistiques[id] = true;
                    positionsConnues[id] = arrivee;
                    animationsJusqua[id] = Date.now() + (arrivee - Math.max(depart, 0)) * 400 + 80;
                    lancerPasAPas(el, { ...pion, position: arrivee }, Math.max(depart, 0));
                }
            }

            const res = await api(ludoUrl + '/bouger', { method: 'POST', body: { pion: id } });
            if (res.ok) {
                if (res.data.arrive) playWinSound();
                if (res.data.mange) playEatSound();
                toast(res.data.message, res.data.terminee ? 'success' : 'info');
                await refresh();
            }
        }

        async function abandonner() {
            if (!confirm('Abandonner la partie ? Le/la partenaire gagnera.')) return;
            const res = await api(ludoUrl + '/abandonner', { method: 'POST' });
            if (res.ok) {
                toast(res.data.message, 'success');
                await refresh();
            }
        }

        async function refresh() {
            const res = await api(ludoUrl + '/etat', { json: false });
            if (res.ok) render(res.data);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const btnMode = document.getElementById('btn-pion-mode');
            if (btnMode) btnMode.textContent = 'Pions : ' + modePionNom(modePion);
            startPolling(ludoUrl + '/etat', render, { interval: 1200 });
        });
    </script>
@endpush