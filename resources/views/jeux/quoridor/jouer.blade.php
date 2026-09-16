@extends('layouts.app')

@section('title', 'Quoridor à deux')

@section('content')
    <div class="fadeIn">
        <div class="center mb8">
            <span class="badge neutre" style="font-size:14px; padding:7px 14px" id="q-status">Chargement…</span>
        </div>

        <div class="q-tete">
            <div class="q-joueur" id="q-j1">—</div>
            <div class="q-barres">
                <div class="q-grab" id="q-barre-h" data-o="h">
                    <div class="q-barre q-barre-h"></div>
                </div>
                <div class="q-grab" id="q-barre-v" data-o="v">
                    <div class="q-barre q-barre-v"></div>
                </div>
            </div>
            <div class="q-joueur" id="q-j2">—</div>
        </div>

        <div class="q-board" id="q-board">
            <div class="q-cells">
                @for ($r = 0; $r < 9; $r++)
                    @php
                        $classeArrivee = '';
                        if ($r === 0) {
                            $classeArrivee = $partie->mode === 'course'
                                ? ' q-ligne-arrivee q-arrivee-des-deux'
                                : ' q-ligne-arrivee q-arrivee-rouge';
                        } elseif ($r === 8 && $partie->mode !== 'course') {
                            $classeArrivee = ' q-ligne-arrivee q-arrivee-bleue';
                        }
                    @endphp
                    @for ($c = 0; $c < 9; $c++)
                        <div class="q-cell{{ $classeArrivee }}"
                             id="qcell-{{ $r }}-{{ $c }}" data-r="{{ $r }}" data-c="{{ $c }}"
                             onclick="onCellClick(this)"></div>
                    @endfor
                @endfor
            </div>
            <div class="q-couche" id="q-couche-pions"></div>
            <div class="q-couche" id="q-couche-murs"></div>
            <div class="q-couche" id="q-couche-slots"></div>
        </div>

        <div id="q-fin" style="display:none" class="card mt16">
            <div class="center">
                <div style="font-size:34px">🏆</div>
                <h2 style="font-size:18px" id="q-fin-titre"></h2>
                <p class="muted tiny" id="q-fin-sous-titre"></p>
                <a href="{{ route('quoridor.index') }}" class="btn btn-sm btn-primary mt8">Nouvelle partie / Retour</a>
            </div>
        </div>

        <p class="tiny muted center mt8">💡 Avance en tapant une case en jaune, ou attrape une barre (Horizontal ↔ / Vertical ↕) et glisse-la dans le plateau. Le premier arrivé gagne : +25 points.</p>

        <div class="center" style="margin-top:96px">
            <button id="q-btn-abandonner" class="btn btn-sm btn-danger-outline" style="display:none" onclick="abandonner()">Abandonner</button>
        </div>
    </div>
@endsection

@push('head')
    <style>
        :root { --q-gap: 2.444%; --q-u: calc((100% - 8 * var(--q-gap)) / 9); }
        .q-tete { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:8px; }
        .q-joueur {
            font-size:13px; font-weight:700; text-align:center; max-width:130px;
            background:var(--card); border:1px solid var(--border); border-radius:12px; padding:5px 10px;
        }
        .q-joueur.tour { border-color:var(--primary-2); box-shadow:0 0 0 2px rgba(90,180,160,.35); }
        .q-joueur .pdot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; vertical-align:middle; }
        .pdot.rouge { background:#e63946; }
        .pdot.bleue { background:#3498db; }
        .q-joueur .nbr { color:var(--text-3); font-size:11px; font-weight:600; }
        .q-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }

        .q-board {
            position:relative; aspect-ratio:1; max-width:560px; margin:0 auto;
            background:#f2efe8; border:3px solid #d8d3c5; border-radius:16px; overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.35);
        }
        .q-cells { display:grid; grid-template-columns:repeat(9,1fr); grid-template-rows:repeat(9,1fr); gap:var(--q-gap); width:100%; height:100%; }
        .q-cell { position:relative; border-radius:4px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.05); cursor:default; }
        .q-cell.q-ligne-arrivee { background:rgba(240,198,105,.18); }
        .q-cell.q-ligne-arrivee.q-arrivee-rouge { background:rgba(230,57,70,.22); }
        .q-cell.q-ligne-arrivee.q-arrivee-bleue { background:rgba(52,152,219,.22); }
        .q-cell.q-ligne-arrivee.q-arrivee-des-deux { background:linear-gradient(90deg, rgba(230,57,70,.22) 0 50%, rgba(52,152,219,.22) 50% 100%); }
        .q-cell.q-cible {
            background:rgba(255,215,0,.28);
            cursor:pointer;
            box-shadow:inset 0 0 0 2px rgba(255,190,0,.85), inset 0 0 0 4px rgba(255,255,255,.35);
            animation:qCible .7s ease-in-out infinite;
        }
        @keyframes qCible {
            0%,100% { background:rgba(255,215,0,.24); }
            50% { background:rgba(255,215,0,.42); }
        }

        .q-couche { position:absolute; inset:0; pointer-events:none; }
        #q-couche-slots { pointer-events:none; }
        .q-pion {
            position:absolute; width:calc(var(--q-u) * 0.78); height:calc(var(--q-u) * 0.78);
            border-radius:50%;
            transform:translate(-50%,-50%);
            transition:left .08s ease, top .08s ease;
            box-shadow: inset -3px -5px 7px rgba(0,0,0,.25), inset 3px 4px 6px rgba(255,255,255,.25), 0 3px 6px rgba(0,0,0,.42);
        }
        .q-pion::before {
            content:''; position:absolute; width:58%; height:58%; border-radius:50%;
            background:radial-gradient(circle at 35% 30%, rgba(255,255,255,.95), rgba(255,255,255,0) 62%);
        }
        .q-pion-rouge { background:radial-gradient(circle at 50% 38%, #ff8f8f 0%, #e63946 55%, #8f1420 100%); }
        .q-pion-bleue { background:radial-gradient(circle at 50% 38%, #8fd0f8 0%, #2c80c4 55%, #123f66 100%); }
        .q-pion.tour { outline:3px dashed rgba(0,0,0,.35); outline-offset:3px; }

        .q-mur {
            position:absolute; background:#3a3632; border-radius:3px;
            box-shadow:0 2px 4px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.18);
        }
        .q-mur-rouge { background:#c5303e; }
        .q-mur-bleue { background:#2f74b5; }
        .q-slot {
            position:absolute; box-sizing:border-box;
            border:2px dashed rgba(50,45,40,.4); border-radius:3px;
            background:rgba(255,215,0,.12);
            cursor:pointer; pointer-events:auto;
            animation:qSlotPulse .9s ease-in-out infinite;
        }
        .q-slot:hover { background:rgba(255,215,0,.34); border-color:rgba(50,45,40,.7); }
        @keyframes qSlotPulse {
            0%,100% { opacity:.65; }
            50% { opacity:1; }
        }
        .q-barres { display:flex; align-items:center; gap:2px; margin:0; user-select:none; -webkit-user-select:none; -webkit-touch-callout:none; }
        .q-barre-choice { display:flex; flex-direction:column; align-items:center; gap:6px; color:var(--text-2); font-size:11px; font-weight:700; white-space:nowrap; user-select:none; -webkit-user-select:none; }
        .q-grab {
            display:inline-flex; align-items:center; justify-content:center;
            padding:22px 26px; cursor:grab;
            touch-action:none; user-select:none; -webkit-user-select:none; -webkit-touch-callout:none;
        }
        .q-barre {
            background:#3a3632; border-radius:4px;
            box-shadow:0 3px 6px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.18);
            cursor:grab; touch-action:none; user-select:none; -webkit-user-select:none; -webkit-touch-callout:none;
            transition:box-shadow .15s ease;
        }
        .q-barre-h { width:56px; height:10px; }
        .q-barre-v { width:10px; height:56px; }
        .q-grab-source { opacity:.35; }
        .q-drag-mur { z-index:6; pointer-events:none; }
        .q-drag-mur-valide { box-shadow:0 2px 6px rgba(0,0,0,.45), 0 0 0 3px rgba(46,204,113,.95), inset 0 0 0 1px rgba(255,255,255,.3); }
        .q-drag-mur-invalide { background:#c0392b; box-shadow:0 2px 6px rgba(0,0,0,.45), 0 0 0 3px rgba(230,57,70,.95), inset 0 0 0 1px rgba(255,255,255,.2); }
        .q-slot-preview { position:absolute; z-index:7; pointer-events:none; border-radius:3px; background:rgba(30,28,26,.95); box-shadow:0 2px 6px rgba(0,0,0,.45), inset 0 0 0 1px rgba(255,255,255,.18); }
        .q-slot-preview-bon { background:rgba(46,204,113,.75); box-shadow:0 0 0 2px rgba(46,204,113,.95), inset 0 0 0 1px rgba(255,255,255,.3); }
        .q-slot-preview-mauvais { background:rgba(230,57,70,.7); box-shadow:0 0 0 2px rgba(230,57,70,.95), inset 0 0 0 1px rgba(255,255,255,.2); }
        .q-slot-preview-ghost { background:rgba(58,54,50,0) !important; z-index:8; }
    </style>
@endpush

@push('scripts')
    <script>
        const qUrl = @json(url('jeux/quoridor/'.$partie->id));
        const moiIdQ = @json(Auth::id());
        const Q_U = 100 / 9;
        const GAP_Q = Q_U * 0.22;
        const CELL_Q = (100 - 8 * GAP_Q) / 9;
        const STEP_Q = CELL_Q + GAP_Q;
        const MUR_L = 2 * CELL_Q + GAP_Q;
        const MUR_T = GAP_Q * 0.9;

        let dernierEtatQ = null;
        let orientationMurQ = null;
        const pionsQ = {};
        const couchePions = document.getElementById('q-couche-pions');
        const coucheMurs = document.getElementById('q-couche-murs');
        const coucheSlots = document.getElementById('q-couche-slots');
        const boardQ = document.getElementById('q-board');
        const dragMurEl = document.createElement('div');
        dragMurEl.className = 'q-mur q-drag-mur';
        dragMurEl.style.display = 'none';
        boardQ.appendChild(dragMurEl);
        const slotMurEl = document.createElement('div');
        slotMurEl.className = 'q-slot-preview';
        slotMurEl.style.display = 'none';
        boardQ.appendChild(slotMurEl);
        const placeMurEl = document.createElement('div');
        placeMurEl.className = 'q-slot-preview';
        placeMurEl.style.display = 'none';
        boardQ.appendChild(placeMurEl);
        let dragMurActif = false;

        function murStyles(w) {
            if (w.o === 'h') {
                return {
                    left: (w.c * STEP_Q) + '%',
                    width: MUR_L + '%',
                    top: ((w.r + 1) * STEP_Q - GAP_Q / 2 - MUR_T / 2) + '%',
                    height: MUR_T + '%',
                };
            }
            return {
                left: ((w.c + 1) * STEP_Q - GAP_Q / 2 - MUR_T / 2) + '%',
                width: MUR_T + '%',
                top: (w.r * STEP_Q) + '%',
                height: MUR_L + '%',
            };
        }

        function centreMur(w) {
            if (w.o === 'h') {
                return {
                    x: w.c * STEP_Q + MUR_L / 2,
                    y: (w.r + 1) * STEP_Q - GAP_Q / 2,
                };
            }
            return {
                x: (w.c + 1) * STEP_Q - GAP_Q / 2,
                y: w.r * STEP_Q + MUR_L / 2,
            };
        }

        function murLePlusProche(o, px, py) {
            const maxR = 7;
            const maxC = 7;
            let meilleur = null;
            let meilleureDistance = Infinity;
            for (let r = 0; r <= maxR; r++) {
                for (let c = 0; c <= maxC; c++) {
                    const m = centreMur({ o, r, c });
                    const d = (px - m.x) * (px - m.x) + (py - m.y) * (py - m.y);
                    if (d < meilleureDistance) { meilleureDistance = d; meilleur = { r, c }; }
                }
            }
            return meilleur;
        }

        function effacer(el) { while (el.firstChild) el.removeChild(el.firstChild); }

        function joueurCle(state, idQ) {
            return state.joueurs.j1.id === idQ ? 'j1' : 'j2';
        }

        function renderQ(state) {
            dernierEtatQ = state;

            const bStatut = document.getElementById('q-status');
            if (bStatut && state) {
                if (state.statut === 'terminee') {
                    bStatut.className = 'badge succes';
                    bStatut.textContent = '🎉 Partie terminée';
                } else {
                    const aMoiStatut = state.tour_id === moiIdQ;
                    bStatut.className = 'badge neutre';
                    bStatut.textContent = aMoiStatut
                        ? '🎯 À toi : déplace ton pion ou pose un mur.'
                        : '⏳ Le/la partenaire joue…';
                }
            }
            Object.values(state.joueurs).forEach(j => {
                const cle = joueurCle(state, j.id);
                const pos = state.pions[cle];
                let el = pionsQ[cle];
                if (!el) {
                    el = document.createElement('div');
                    el.className = 'q-pion q-pion-' + j.couleur;
                    pionsQ[cle] = el;
                    couchePions.appendChild(el);
                }
                el.style.left = (pos.col * STEP_Q + CELL_Q / 2) + '%';
                el.style.top = (pos.row * STEP_Q + CELL_Q / 2) + '%';
                el.classList.toggle('tour', state.statut === 'en_cours' && state.tour_id === j.id);
            });

            effacer(coucheMurs);
            (state.murs || []).forEach(w => {
                const d = document.createElement('div');
                d.className = 'q-mur' + (w.who === 'j1' ? ' q-mur-rouge' : w.who === 'j2' ? ' q-mur-bleue' : '');
                Object.assign(d.style, murStyles(w));
                coucheMurs.appendChild(d);
            });

            ['j1', 'j2'].forEach(cle => {
                const j = state.joueurs && state.joueurs[cle];
                const el = document.getElementById('q-' + cle);
                if (el && j) {
                    el.classList.toggle('tour', state.statut === 'en_cours' && state.tour_id === j.id);
                    const reste = (state.mursRestants && state.mursRestants[cle] !== undefined) ? state.mursRestants[cle] : '?';
                    el.innerHTML = '<span class="pdot ' + j.couleur + '"></span>' + esc(j.name)
                        + '<br><span class="nbr">🧱 ' + reste + '/10</span>';
                }
            });

            const status = document.getElementById('q-status');
            const btnFin = document.getElementById('q-fin');

            if (state.statut === 'terminee' && state.vainqueur_id) {
                const v = objectValuesQ(state.joueurs).find(j => j.id === state.vainqueur_id);
                status.className = 'badge succes';
                status.textContent = '🎉 Partie terminée';
                document.getElementById('q-btn-abandonner').style.display = 'none';
                btnFin.style.display = 'block';
                document.getElementById('q-fin-titre').textContent = 'Victoire de ' + v.name + ' !';
                document.getElementById('q-fin-sous-titre').textContent =
                    v.id === moiIdQ ? 'Tu gagnes +25 points 🎁' : v.name + ' gagne +25 points… revanche ?';
                rendreCibles(null);
                return;
            }

            btnFin.style.display = 'none';
            document.getElementById('q-btn-abandonner').style.display = state.statut === 'en_cours' ? 'inline-block' : 'none';

            if (state.statut === 'terminee') {
                status.className = 'badge neutre';
                status.textContent = 'Partie terminée';
                rendreCibles(null);
                return;
            }

            const aMoi = state.tour_id === moiIdQ;
            if (aMoi) {
                status.className = 'badge neutre';
                status.textContent = '🎯 À toi : déplace ton pion ou pose un mur.';
            } else {
                const opp = objectValuesQ(state.joueurs).find(j => j.id === state.tour_id);
                status.className = 'badge neutre';
                status.textContent = '⏳ ' + (opp ? opp.name : 'Le/la partenaire') + ' joue…';
            }

            rendreCibles(state);
        }

        function rendreCibles(state) {
            const cells = document.querySelectorAll('.q-cell.q-cible');
            cells.forEach(el => el.classList.remove('q-cible'));
            effacer(coucheSlots);

            if (!state || state.statut !== 'en_cours' || state.tour_id !== moiIdQ || !state.legal) return;

            (state.legal.deplacements || []).forEach(d => {
                const cell = document.getElementById('qcell-' + d.row + '-' + d.col);
                if (cell) cell.classList.add('q-cible');
            });
        }

        function onCellClick(cell) {
            if (!cell.classList.contains('q-cible')) return;
            deplacer(parseInt(cell.dataset.r, 10), parseInt(cell.dataset.c, 10));
        }

        function positionMurDrag(e) {
            const rect = boardQ.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const o = orientationMurQ || 'h';
            const horsPlateau = x < 0 || y < 0 || x > rect.width || y > rect.height;
            const wPx = rect.width * (o === 'h' ? MUR_L : MUR_T) / 100;
            const hPx = rect.width * (o === 'h' ? MUR_T : MUR_L) / 100;
            const leftPx = x - wPx / 2;
            const topPx = y - hPx - 48;
            const barCenterY = topPx + hPx / 2;

            const pcx = (x / rect.width) * 100;
            const pcy = (barCenterY / rect.height) * 100;
            const slot = murLePlusProche(o, pcx, pcy);
            const r = slot.r;
            const c = slot.c;
            const valide = (dernierEtatQ && dernierEtatQ.legal && dernierEtatQ.legal.murs || [])
                .some(w => w.r === r && w.c === c && w.o === o);

            slotMurEl.style.display = 'block';
            slotMurEl.style.left = leftPx + 'px';
            slotMurEl.style.top = topPx + 'px';
            slotMurEl.style.width = wPx + 'px';
            slotMurEl.style.height = hPx + 'px';

            if (horsPlateau) {
                placeMurEl.style.display = 'none';
            } else {
                placeMurEl.style.display = 'block';
                placeMurEl.style.left = leftPx + 'px';
                placeMurEl.style.top = topPx + 'px';
                placeMurEl.style.width = wPx + 'px';
                placeMurEl.style.height = hPx + 'px';
                placeMurEl.classList.add('q-slot-preview-ghost');
                placeMurEl.classList.toggle('q-slot-preview-bon', valide);
                placeMurEl.classList.toggle('q-slot-preview-mauvais', !valide);
            }

            dragMurEl.dataset.r = r;
            dragMurEl.dataset.c = c;
            dragMurEl.dataset.o = o;
        }

        function retourAuPointDeDepart(e) {
            const marge = 30;
            for (const id of ['q-barre-h', 'q-barre-v']) {
                const el = document.getElementById(id);
                if (!el) continue;
                const r = el.getBoundingClientRect();
                if (e.clientX >= r.left - marge && e.clientX <= r.right + marge
                    && e.clientY >= r.top - marge && e.clientY <= r.bottom + marge) {
                    return true;
                }
            }
            return false;
        }

        function onMurPointerDown(e) {
            if (!dernierEtatQ || dernierEtatQ.statut !== 'en_cours' || dernierEtatQ.tour_id !== moiIdQ) {
                dragMurActif = false;
                return;
            }
            e.preventDefault();
            const o = e.currentTarget.dataset.o;
            orientationMurQ = o;
            dragMurActif = true;
            e.currentTarget.classList.add('q-grab-source');
            const couleurCoulante = joueurCle(dernierEtatQ, moiIdQ) === 'j1' ? 'q-mur-rouge' : 'q-mur-bleue';
            dragMurEl.classList.toggle(couleurCoulante, true);
            positionMurDrag(e);
            e.currentTarget.setPointerCapture(e.pointerId);
        }

        function onMurPointerMove(e) {
            if (!dragMurActif) return;
            positionMurDrag(e);
        }

        async function onMurPointerUp(e) {
            if (!dragMurActif) return;
            dragMurActif = false;
            const source = document.getElementById('q-barre-' + (orientationMurQ || 'h'));
            if (source) source.classList.remove('q-grab-source');
            dragMurEl.style.display = 'none';
            slotMurEl.style.display = 'none';
            placeMurEl.style.display = 'none';
            if (retourAuPointDeDepart(e)) return;
            const r = parseInt(dragMurEl.dataset.r, 10);
            const c = parseInt(dragMurEl.dataset.c, 10);
            const o = dragMurEl.dataset.o;
            if (!Number.isFinite(r) || !Number.isFinite(c) || !o) return;
            const valide = (dernierEtatQ && dernierEtatQ.legal && dernierEtatQ.legal.murs || [])
                .some(w => w.r === r && w.c === c && w.o === o);
            if (valide) {
                placerMur(r, c, o);
            } else {
                slotMurEl.style.display = 'block';
                Object.assign(slotMurEl.style, murStyles({ r, c, o }));
                slotMurEl.classList.add('q-slot-preview-mauvais');
                setTimeout(() => {
                    slotMurEl.style.display = 'none';
                    slotMurEl.classList.remove('q-slot-preview-bon', 'q-slot-preview-mauvais');
                }, 260);
            }
        }

        ['q-barre-h', 'q-barre-v'].forEach(id => {
            const b = document.getElementById(id);
            b.addEventListener('pointerdown', onMurPointerDown);
            b.addEventListener('pointermove', onMurPointerMove);
            b.addEventListener('pointerup', onMurPointerUp);
            b.addEventListener('pointercancel', onMurPointerUp);
            b.addEventListener('contextmenu', e => e.preventDefault());
        });

        async function deplacer(row, col) {
            playQ('tick');
            const monCle = joueurCle(dernierEtatQ, moiIdQ);
            if (pionsQ[monCle]) {
                pionsQ[monCle].style.left = (col * STEP_Q + CELL_Q / 2) + '%';
                pionsQ[monCle].style.top = (row * STEP_Q + CELL_Q / 2) + '%';
            }
            const res = await api(qUrl + '/jouer', { method: 'POST', body: { type: 'pion', row, col } });
            if (!res.ok) return;
            if (res.data.terminee) playQ('win');
            toast(res.data.message, res.data.terminee ? 'success' : 'info');
            await refreshQ();
        }

        async function placerMur(r, c, o) {
            playQ('mur');
            const who = joueurCle(dernierEtatQ, moiIdQ);
            dernierEtatQ.murs = (dernierEtatQ.murs || []).concat([{ r, c, o, who }]);
            effacer(coucheMurs);
            (dernierEtatQ.murs || []).forEach(w => {
                const d = document.createElement('div');
                d.className = 'q-mur' + (w.who === 'j1' ? ' q-mur-rouge' : w.who === 'j2' ? ' q-mur-bleue' : '');
                Object.assign(d.style, murStyles(w));
                coucheMurs.appendChild(d);
            });
            const res = await api(qUrl + '/jouer', { method: 'POST', body: { type: 'mur', r, c, o } });
            if (!res.ok) { await refreshQ(); return; }
            toast(res.data.message, 'info');
            await refreshQ();
        }

        async function abandonner() {
            if (!confirm('Abandonner la partie ? Le/la partenaire gagnera.')) return;
            const res = await api(qUrl + '/abandonner', { method: 'POST' });
            if (res.ok) { toast(res.data.message, 'success'); await refreshQ(); }
        }

        async function refreshQ() {
            const res = await api(qUrl + '/etat', { json: false });
            if (res.ok) renderQ(res.data);
        }

        function objectValuesQ(o) { return Object.keys(o).map(k => o[k]); }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        let qAudio = null;
        function unlockQA() {
            if (qAudio) return;
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            try { qAudio = new AC(); qAudio.resume().catch(() => {}); } catch (e) { qAudio = null; }
        }
        ['pointerdown', 'touchstart', 'keydown'].forEach((ev) =>
            document.addEventListener(ev, () => unlockQA(), { once: true, passive: true })
        );
        function bipQ(freq, vol, durMs, type) {
            if (!qAudio) return;
            const osc = qAudio.createOscillator();
            const gain = qAudio.createGain();
            const d = durMs / 1000;
            osc.type = type || 'sine';
            osc.frequency.setValueAtTime(freq, qAudio.currentTime);
            gain.gain.setValueAtTime(vol, qAudio.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, qAudio.currentTime + d);
            osc.connect(gain);
            gain.connect(qAudio.destination);
            osc.start();
            osc.stop(qAudio.currentTime + d);
        }
        function playQ(son) {
            if (!qAudio) unlockQA();
            if (!qAudio) return;
            try {
                if (qAudio.state === 'suspended') qAudio.resume().catch(() => {});
                if (son === 'mur') { bipQ(160, 0.4, 90, 'triangle'); bipQ(120, 0.35, 120, 'sine'); }
                else if (son === 'win') { bipQ(523.25, 0.3, 160, 'sine'); bipQ(659.25, 0.3, 160, 'sine'); bipQ(783.99, 0.3, 160, 'sine'); bipQ(1046.5, 0.35, 320, 'sine'); }
                else { bipQ(340, 0.3, 55, 'triangle'); }
            } catch (e) { /* silence */ }
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(qUrl + '/etat', renderQ, { interval: 1200 });
        });
    </script>
@endpush