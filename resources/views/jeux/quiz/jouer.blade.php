@extends('layouts.app')

@section('title', 'Tu me connais ?')

@push('head')
    <style>
        .roulette-wrap {
            position: relative;
            width: 240px;
            height: 240px;
            margin: 0 auto;
        }
        .roulette {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(
                #ff6b9d 0deg 45deg, #7c4dff 45deg 90deg, #ff6b9d 90deg 135deg, #7c4dff 135deg 180deg,
                #ff6b9d 180deg 225deg, #7c4dff 225deg 270deg, #ff6b9d 270deg 315deg, #7c4dff 315deg 360deg
            );
            box-shadow: 0 10px 28px rgba(0, 0, 0, .22);
            transition: transform 2.6s cubic-bezier(.22, .61, .24, 1);
            will-change: transform;
            touch-action: none;
        }
        .roulette::after {
            content: '';
            position: absolute;
            inset: 20%;
            border-radius: 50%;
            background: radial-gradient(circle, #fff 0%, #fdf2ff 100%);
            box-shadow: inset 0 0 0 2px rgba(0, 0, 0, .08);
        }
        .roulette-pointer {
            position: absolute;
            top: -12px;
            left: 50%;
            width: 0;
            height: 0;
            border-left: 16px solid transparent;
            border-right: 16px solid transparent;
            border-top: 26px solid #e11d48;
            transform: translateX(-50%);
            z-index: 3;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .35));
        }
        .roulette-emoji {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: -12px 0 0 -12px;
            z-index: 2;
            transform: rotate(calc(var(--i) * 45deg + 22.5deg)) translateY(-96px) rotate(calc(var(--i) * -45deg - 22.5deg));
        }
        .roulette-hub {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            font-size: 26px;
            background: #fff;
            border-radius: 50%;
            width: 62px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .18);
        }
        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: rgba(0, 0, 0, .14);
            display: inline-block;
        }
        .dot.fait {
            background: var(--accent, #ff6b9d);
        }
    </style>
@endpush

@section('content')
    <div class="fadeIn">
        <div class="center mb16">
            <span class="badge neutre" style="font-size:15px; padding:8px 16px" id="quiz-status">Partie en cours</span>
            <div class="tiny muted mt8" id="quiz-compteur"></div>
            <div id="quiz-progress" class="mt8"></div>
            <p id="pool-alerte" class="tiny muted" style="display:none; padding:8px 12px; border-radius:8px; background:rgba(0,0,0,.04)"></p>
        </div>

        <div id="quiz-stage" class="mt16">
            <div class="center" style="padding:40px"><div class="spinner"></div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const quizUrl = @json(url('jeux/tu-me-connais/'.$session->id));
        const emojis = ['💘', '🍀', '🌹', '🎈', '✨', '🦋', '🌙', '☀️'];
        let stateData = null;
        let rotation = 0;
        let lastRenderKey = '';

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function balisemWheel() {
            const disc = document.getElementById('roulette');
            emojis.forEach((emoji, i) => {
                const span = document.createElement('span');
                span.className = 'roulette-emoji';
                span.style.setProperty('--i', i);
                span.textContent = emoji;
                disc.appendChild(span);
            });
        }

        function render() {
            const stage = document.getElementById('quiz-stage');
            const statusEl = document.getElementById('quiz-status');
            const compteur = document.getElementById('quiz-compteur');
            const progress = document.getElementById('quiz-progress');
            const alerte = document.getElementById('pool-alerte');

            alerte.style.display = stateData.poolEpuise ? 'block' : 'none';
            if (stateData.poolEpuise) {
                alerte.textContent = '💡 Vous avez déjà vu toutes les questions du quiz — elles sont reprises au hasard, certaines peuvent se répéter.';
            }

            compteur.textContent = stateData.status === 'terminee'
                ? 'Tous les jugements sont rendus !'
                : `Tour ${Math.min(stateData.jugees + 1, stateData.total)} / ${stateData.total}`;

            progress.innerHTML = Array.from({ length: stateData.total }, (_, i) =>
                `<span class="dot ${i < stateData.jugees ? 'fait' : ''}"></span>`
            ).join(' ');

            statusEl.className = stateData.status === 'terminee' ? 'badge succes' : 'badge neutre';
            statusEl.textContent = stateData.status === 'terminee' ? '🎉 Terminé' : 'Partie en cours';

            // Ne pas ré-écrire la question tant que l'état n'a pas bougé :
            // le polling (1,5 s) effacerait sinon ce que le joueur est en train de taper.
            const key = renderKey();
            if (key === lastRenderKey) {
                return;
            }
            lastRenderKey = key;

            if (stateData.status === 'terminee') {
                stage.innerHTML = renderFinale();
                return;
            }

            if (stateData.question) {
                stage.innerHTML = renderQuestion(stateData.question);
                return;
            }

            stage.innerHTML = renderRoulette();
        }

        function renderKey() {
            const q = stateData.question;
            return JSON.stringify([
                stateData.status,
                stateData.tourDe ? stateData.tourDe.id : null,
                q ? [q.id, q.maReponse, q.saReponse, q.resultat, q.bonneReponse] : null,
            ]);
        }

        function renderRoulette() {
            const moiTour = stateData.tourDe !== null && stateData.tourDe.id === stateData.moi.id;
            let msg = '';
            if (stateData.tourDe === null) {
                msg = '⏳ En attente de ta question';
            } else if (moiTour) {
                msg = `Ton tour, ${stateData.moi.name} ! Fais tourner la roulette 🎡`;
            } else {
                msg = `C'est au tour de <b>${esc(stateData.tourDe.name)}</b> de faire tourner la roulette…`;
            }

            const dernier = stateData.dernier ? `
                <div class="card pad-sm mb16">
                    <div class="tiny muted mb8">Résultat du tirage précédent</div>
                    ${renderResultat(stateData.dernier)}
                </div>` : '';

            return `${dernier}
                <div class="card center pulse-glow" style="border-color:rgba(234,88,12,.45)">
                    <div class="mb16" style="font-size:15px">${msg}</div>
                    <div class="roulette-wrap">
                        <div class="roulette-pointer"></div>
                        <div class="roulette" id="roulette"><div class="roulette-hub">🎯</div></div>
                    </div>
                    <div class="mt16" style="min-height:44px">
                        ${moiTour ? `<button class="btn btn-primary" id="btn-spin">🎡 Tourner la roulette</button>` : '<span class="tiny muted">La roulette apparaîtra quand ce sera ton tour</span>'}
                    </div>
                </div>`;
        }

        function renderQuestion(q) {
            const num = q.ordre + 1;
            const cibleTag = `<span class="tiny muted">À propos de <b>${esc(q.cible)}</b></span>`;
            const cat = q.categorie ? `<span class="tiny muted">· ${esc(q.categorie)}</span>` : '';
            let body = '';

            if (q.resultat) {
                body = renderResultat(q);
            } else if (q.jeSuisCible) {
                body = q.saReponse
                    ? renderJugement(q)
                    : `<div class="badge neutre mt8">⏳ En attente de la réponse de ${esc(stateData.partner.name)}…</div>`;
            } else if (!q.maReponse) {
                body = `
                    <form class="flex gap8 mt8" onsubmit="event.preventDefault(); repondre(${q.id}, this)">
                        <input class="input" id="rep-${q.id}" name="reponse" maxlength="255" placeholder="Réponds à sa place…" required>
                        <button class="btn btn-sm btn-primary">Envoyer</button>
                    </form>
                    <div class="tiny muted mt8">Prouve que tu connais ${esc(q.cible)}…</div>`;
            } else {
                body = `<div class="badge neutre mt8">⏳ ${esc(q.cible)} juge ta réponse…</div>`;
            }

            return `
                <div class="card pad-sm">
                    <div class="flex between items-center mb8" style="flex-wrap:wrap; gap:4px">
                        <div>${cibleTag} ${cat}</div>
                        <span class="tiny muted">Question ${num} / ${stateData.total}</span>
                    </div>
                    <div style="font-size:15px; line-height:1.5">${esc(q.texte)}</div>
                    ${body}
                </div>`;
        }

        function renderJugement(q) {
            return `
                <div class="chip mt8">🧠 ${esc(stateData.partner.name)} a répondu : <b style="margin-left:4px">${esc(q.saReponse)}</b></div>
                <div class="small muted mt8">Dis si c'est vrai ou faux :</div>
                <div class="flex gap8 mt8" style="flex-wrap:wrap">
                    <button class="btn btn-sm btn-primary" onclick="juger(${q.id}, true)">✅ C'est vrai</button>
                    <button class="btn btn-sm btn-danger" onclick="showInv(${q.id})">❌ C'est faux</button>
                </div>
                <div id="inv-${q.id}" style="display:none" class="flex gap8 mt8">
                    <input class="input" id="br-${q.id}" maxlength="255" placeholder="La vraie réponse…">
                    <button class="btn btn-sm btn-danger" onclick="juger(${q.id}, false)">Valider le faux</button>
                </div>`;
        }

        function renderResultat(q) {
            const chip = q.jeSuisCible
                ? `<span class="chip">Réponse de ${esc(stateData.partner.name)} : <b style="margin-left:4px">${esc(q.saReponse)}</b></span>`
                : `<span class="chip">Ta réponse : <b style="margin-left:4px">${esc(q.maReponse)}</b></span>`;

            let result = '';
            if (q.resultat === 'match') {
                result = q.jeSuisCible
                    ? `<div class="badge succes mt8">✅ ${esc(stateData.partner.name)} t'a connu(e) ! <b>+10 pts pour ${esc(stateData.partner.name)}</b></div>`
                    : `<div class="badge succes mt8">✅ ${esc(q.cible)} a confirmé ! C'était bien. <b>+10 pts pour toi</b></div>`;
            } else {
                const vraie = q.bonneReponse ? ` « ${esc(q.bonneReponse)} »` : " c'était faux.";
                result = q.jeSuisCible
                    ? `<div class="badge warning mt8" style="background:rgba(255,176,32,.14);color:var(--warning)">❌ ${esc(stateData.partner.name)} n'a pas trouvé. La vraie réponse était${vraie}</div>`
                    : `<div class="badge warning mt8" style="background:rgba(255,176,32,.14);color:var(--warning)">❌ Pas cette fois… La vraie réponse était${vraie}</div>`;
            }

            return `<div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">${chip}</div>${result}`;
        }

        function renderFinale() {
            const surPartenaire = stateData.conclus.filter(q => q.resultat === 'match' && !q.jeSuisCible).length;
            const surMoi = stateData.conclus.filter(q => q.resultat === 'match' && q.jeSuisCible).length;
            const message = surPartenaire >= 3
                ? 'Impossible de te mentir !'
                : (surPartenaire === 2 ? 'Pas mal, continue à observer…' : 'Il reste de la découverte à faire 😏');

            return `<div class="card center">
                <div style="font-size:46px">🏆</div>
                <h2 style="font-size:18px">Tu connais ${esc(stateData.partner.name)} : ${surPartenaire} / 4</h2>
                <p class="muted tiny">${message}</p>
                <div class="flex gap8 mt8 center" style="flex-wrap:wrap">
                    <div class="chip">✅ ${esc(stateData.moi.name)} : ${surPartenaire} connus</div>
                    <div class="chip">✅ ${esc(stateData.partner.name)} : ${surMoi} connus</div>
                </div>
                <a href="{{ route('quiz.index') }}" class="btn btn-sm btn-primary mt8">Rejouer</a>
            </div>`;
        }

        function showInv(id) {
            const el = document.getElementById('inv-' + id);
            if (el) el.style.display = 'flex';
        }

        async function tourner() {
            const btn = document.getElementById('btn-spin');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.textContent = '🎡 La roulette tourne…';

            rotation += 1080 + Math.floor(Math.random() * 360);
            const disc = document.getElementById('roulette');
            if (disc) disc.style.transform = `rotate(${rotation}deg)`;

            await new Promise(r => setTimeout(r, 2600));

            const res = await api(quizUrl + '/reveler', { method: 'POST' });
            if (res.ok) {
                await refresh();
            } else {
                toast(res.data.error || "Impossible de révéler la question.", 'error');
                btn.disabled = false;
                btn.textContent = '🎡 Tourner la roulette';
            }
        }

        async function repondre(sessionQuestionId, form) {
            const input = form.querySelector('input');
            if (!input.value.trim()) return;
            const res = await api(quizUrl + '/repondre', {
                method: 'POST',
                body: { question_id: sessionQuestionId, reponse: input.value },
            });
            if (res.ok) {
                await refresh();
            }
        }

        async function juger(sessionQuestionId, correct) {
            const bonneReponse = correct ? null : (document.getElementById('br-' + sessionQuestionId)?.value || null);
            if (!correct && !bonneReponse) {
                toast('Indique la vraie réponse.', 'error');
                return;
            }
            const res = await api(quizUrl + '/juger', {
                method: 'POST',
                body: { question_id: sessionQuestionId, correct, bonne_reponse: bonneReponse },
            });
            if (res.ok) await refresh();
        }

        async function refresh() {
            const res = await api(quizUrl + '/etat', { json: false });
            if (res.ok) {
                stateData = res.data;
                render();
            }
        }

        document.addEventListener('click', (e) => {
            if (e.target && e.target.id === 'btn-spin') tourner();
        });

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(quizUrl + '/etat', (data) => {
                stateData = data;
                render();
                const disc = document.getElementById('roulette');
                if (disc && !disc.querySelector('.roulette-emoji')) balisemWheel();
            }, { interval: 1500 });
        });
    </script>
@endpush