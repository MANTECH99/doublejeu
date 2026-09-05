@extends('layouts.app')

@section('title', 'Mots Croisés du Couple')

@section('content')
    @php $partenaire = $couple->partnerOf(auth()->user()); @endphp
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🧩</div>
            <h1 class="title">Mots Croisés du Couple</h1>
            <p class="subtitle">Toi tu inventes ta grille, {{ $partenaire->name }} remplit la sienne… et chacun observe l'autre en temps réel.</p>
        </div>
        <div class="center mt8">
            <a class="btn btn-sm btn-ghost" href="{{ route('mots-croises.mots') }}">🧳 Mes mots</a>
        </div>

        <section class="section-head"><h2>🎁 Ta grille pour {{ $partenaire->name }}</h2><span class="tiny muted">Observe {{ $partenaire->name }} remplir ce que tu as inventé</span></section>
        <div class="card pad-sm">
            <div id="mc-observe">
                <div class="center" style="padding:30px"><div class="spinner"></div></div>
            </div>
        </div>

        <section class="section-head"><h2>🧩 La grille de {{ $partenaire->name }} pour toi</h2><span class="tiny muted">Résous-la : remplis chaque mot complet, s'il est juste il se fixe 🎯</span></section>
        <div class="card mt16" style="border-color:rgba(232,121,249,.4)">
            <div class="tiny muted center" id="mc-solve-progress">…</div>
            <div id="mc-solve">
                <div class="center" style="padding:40px"><div class="spinner"></div></div>
            </div>
        </div>

        <div class="card pad-sm mt16" id="mc-indices" style="display:none">
            <div class="tiny muted">Indices de {{ $partenaire->name }}</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const mcUrl = @json(url('jeux/mots-croises'));
        const mcMotsUrl = @json(url('jeux/mots-croises/mots'));
        let mcState = null;

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function mcKey(r, c) { return r + ',' + c; }

        function buildGrilleHtml(d, editable) {
            if (!d || !d.cases || !d.noires) return '';
            const noires = new Set(d.noires);
            const numeros = d.numeros || {};
            const brouillon = d.brouillon || {};
            const cellW = Math.min(46, Math.max(30, Math.floor((window.innerWidth - 96) / d.colonnes)));
            const rows = Array.from({ length: d.lignes }, (_, r) => Array.from({ length: d.colonnes }, (_, c) => {
                const key = mcKey(r, c);
                if (noires.has(key)) return `<div class="mc-cell noir"></div>`;
                const num = numeros[key];
                const lettre = d.cases[key] ?? '';
                const draft = brouillon[key] ?? '';
                if (!editable) {
                    const aff = lettre || draft;
                    const cls = lettre ? 'rempli' : (draft ? 'en-cours' : 'vide');
                    return `<div class="mc-cell ${cls}">${num ? `<span class="mc-num">${num}</span>` : ''}${aff ? `<span class="mc-lettre ${cls}">${esc(aff)}</span>` : ''}</div>`;
                }
                if (lettre) {
                    return `<div class="mc-cell rempli">${num ? `<span class="mc-num">${num}</span>` : ''}<span class="mc-lettre">${esc(lettre)}</span></div>`;
                }
                return `<div class="mc-cell vide ${draft ? 'en-cours' : ''}">
                    ${num ? `<span class="mc-num">${num}</span>` : ''}
                    <input class="mc-input" maxlength="1" data-r="${r}" data-c="${c}" value="${esc(draft)}" autocomplete="off" inputmode="text" aria-label="Case ${num || ''}">
                </div>`;
            }).join('')).join('');
            return `<div class="mc-grid" style="--cols:${d.colonnes}; --cw:${cellW}px">${rows}</div>`;
        }

        function renderObserve() {
            const box = document.getElementById('mc-observe');
            const s = mcState;
            if (!s) return;
            const nom = s.partenaire;
            const g = s.maGrillePourX;

            if (!g) {
                box.innerHTML = `<div class="tiny muted center" style="padding:16px">`
                    + (s.mesMots >= 3
                        ? `Prêt(e) à créer ta grille pour ${esc(nom)} avec tes mots perso ?`
                        : `Ajoute d'abord au moins 3 mots dans <a href="${mcMotsUrl}">🧳 Mes mots</a> (${s.mesMots} pour l'instant).`)
                    + `</div>`
                    + (s.mesMots >= 3
                        ? `<div class="center mt8"><button class="btn btn-sm btn-primary" onclick="genererGrille()">🧩 Générer la grille pour ${esc(nom)}</button></div>`
                        : `<div class="center mt8"><a class="btn btn-sm btn-soft" href="${mcMotsUrl}">🧳 Créer mes mots</a></div>`);
                return;
            }

            box.innerHTML = `<div class="tiny muted center">${esc(nom)} a trouvé <b>${g.progress.trouvees}</b> / ${g.progress.total} lettres ${g.complete ? '🎉 Grille terminée !' : '…'}</div>`
                + buildGrilleHtml(g, false)
                + `<div class="flex gap8 mt8" style="justify-content:center">
                        <button class="btn btn-sm btn-ghost" onclick="genererGrille()">🔁 Générer une nouvelle grille</button>
                        <a class="btn btn-sm" href="${mcMotsUrl}">🧳 Revoir mes mots</a>
                    </div>`;
        }

        function renderSolve() {
            const box = document.getElementById('mc-solve');
            const prog = document.getElementById('mc-solve-progress');
            const indices = document.getElementById('mc-indices');
            const s = mcState;
            const nom = s && s.partenaire;
            const g = s && s.aGrillePourMoi;

            if (!g) {
                box.innerHTML = `<div class="tiny muted center" style="padding:24px">⏳ ${esc(nom || 'Ta partenaire')} n'a pas encore créé ta grille. Patience…</div>`;
                if (prog) prog.textContent = '';
                indices.style.display = 'none';
                return;
            }

            if (prog) prog.textContent = g.complete
                ? '🏆 Grille terminée ! Bravo ' + esc(nom)
                : ('🔎 ' + g.progress.trouvees + ' / ' + g.progress.total + ' lettres');

            box.innerHTML = buildGrilleHtml(g, true) + `<div class="tiny muted center mt8">Clique les cases du mot, tape ses lettres : la vérification se fait quand il est complet 🎯</div>`;
            box.querySelectorAll('.mc-input').forEach(inp => {
                inp.addEventListener('input', () => onMcInput(inp));
                inp.addEventListener('blur', () => inp.classList.remove('actif'));
                inp.addEventListener('focus', () => inp.classList.add('actif'));
            });

            indices.style.display = 'block';
            indices.innerHTML = `<div class="tiny muted mb8">Indices de ${esc(nom)}</div>`
                + g.mots.map(m => `
                    <div class="row">
                        <span class="chip" style="min-width:34px; justify-content:center; font-weight:800">${m.numero}</span>
                        <div class="grow" style="font-size:14px; line-height:1.5">
                            <span class="tiny muted">${m.orientation === 'h' ? '➡️ horizontal' : '⬇️ vertical'}</span>
                            <span class="tag subtle">${m.taille} lettres</span>
                            <div>${esc(m.indice)}</div>
                        </div>
                    </div>`).join('');
        }

        function renderAll() {
            const box = document.getElementById('mc-solve');
            let focusCle = null;
            if (box) {
                const actif = box.querySelector('.mc-input:focus');
                if (actif) focusCle = actif.dataset.r + ',' + actif.dataset.c;
            }
            renderObserve();
            renderSolve();
            if (focusCle) {
                const [fr, fc] = focusCle.split(',');
                const el = box.querySelector(`.mc-input[data-r="${fr}"][data-c="${fc}"]`);
                if (el && !el.disabled) el.focus();
            }
        }

        let mcBusy = false;

        async function onMcInput(inp) {
            if (mcBusy) return;
            let val = (inp.value || '').slice(-1);
            val = val.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
            if (val !== '' && !/^[A-Z]$/.test(val)) {
                inp.value = '';
                return;
            }
            const r = inp.dataset.r, c = inp.dataset.c;
            mcBusy = true;
            const res = await api(mcUrl + '/verifier', {
                method: 'POST',
                body: { r: +r, c: +c, lettre: val },
            });
            mcBusy = false;
            if (res.ok && res.data.etat) {
                const etat = res.data.etat;
                mcState.aGrillePourMoi = etat;
                const statuts = res.data.statuts || {};
                let motJuste = false;
                Object.values(statuts).forEach((s) => {
                    if (s.statut === 'correct') {
                        motJuste = true;
                    } else if (s.statut === 'incorrect') {
                        s.cases.forEach(k => flashCell(k));
                    }
                });
                updateSolve(etat);
                renderObserve();
                if (res.data.points_gagnes > 0) {
                    toast('+' + res.data.points_gagnes + ' pts ⚡', 'success');
                }
                if (etat.complete) {
                    toast('🧩 Mots croisés complétés !', 'success');
                } else if (motJuste) {
                    focusProchaine();
                } else {
                    avancerApres(mcKey(+r, +c), etat);
                }
            } else {
                inp.value = '';
            }
        }

        function updateSolve(etat) {
            const box = document.getElementById('mc-solve');
            if (!box) return;
            box.querySelectorAll('.mc-input').forEach(inp => {
                const k = mcKey(+inp.dataset.r, +inp.dataset.c);
                const locked = etat.cases[k] || '';
                const draft = (etat.brouillon && etat.brouillon[k]) || '';
                if (locked) {
                    inp.value = locked;
                    inp.disabled = true;
                    inp.classList.add('bon');
                    inp.classList.remove('erreur');
                } else {
                    inp.value = draft;
                    inp.disabled = false;
                    inp.classList.remove('bon');
                }
            });
            const prog = document.getElementById('mc-solve-progress');
            if (prog) {
                prog.textContent = etat.complete
                    ? '🏆 Grille terminée ! Bravo ' + esc(mcState.partenaire)
                    : ('🔎 ' + etat.progress.trouvees + ' / ' + etat.progress.total + ' lettres');
            }
        }

        function flashCell(k) {
            const [fr, fc] = k.split(',');
            const el = document.querySelector(`#mc-solve .mc-input[data-r="${fr}"][data-c="${fc}"]`);
            if (!el) return;
            el.classList.add('erreur');
            clearTimeout(el._flash);
            el._flash = setTimeout(() => el.classList.remove('erreur'), 750);
        }

        function avancerApres(k, etat) {
            const [r, c] = k.split(',').map(Number);
            const noires = new Set(etat.noires);
            for (const m of (etat.mots || [])) {
                const dr = m.orientation === 'h' ? 0 : 1;
                const dc = m.orientation === 'h' ? 1 : 0;
                for (let i = 0; i < m.taille; i++) {
                    if (m.position[0] + dr * i !== r || m.position[1] + dc * i !== c) continue;
                    if (i + 1 >= m.taille) break;
                    const nk = mcKey(m.position[0] + dr * (i + 1), m.position[1] + dc * (i + 1));
                    if (noires.has(nk)) break;
                    if (etat.cases[nk] || (etat.brouillon && etat.brouillon[nk])) break;
                    const el = document.querySelector(`#mc-solve .mc-input[data-r="${m.position[0] + dr * (i + 1)}"][data-c="${m.position[1] + dc * (i + 1)}"]`);
                    if (el && !el.disabled) {
                        el.focus();
                        el.select();
                    }
                    return;
                }
            }
            focusProchaine();
        }

        function focusProchaine() {
            const focusable = [...document.querySelectorAll('#mc-solve .mc-input:not(.bon)')].filter(inp => !inp.disabled);
            focusable[0]?.focus();
        }

        async function genererGrille() {
            const res = await api(mcUrl + '/generer', {
                method: 'POST',
                body: {},
            });
            if (res.ok) {
                toast('Grille générée pour ' + mcState.partenaire + ' 🧩', 'success');
                refresh();
            } else {
                toast((res.data && res.data.error) || 'Génération impossible.', 'error');
            }
        }

        async function refresh() {
            const res = await api(mcUrl + '/etat', { json: false });
            if (res.ok) {
                mcState = res.data;
                renderAll();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(mcUrl + '/etat', (data) => { mcState = data; renderAll(); }, { interval: 1000 });
        });
    </script>
@endpush