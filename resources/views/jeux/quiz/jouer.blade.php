@extends('layouts.app')

@section('title', 'Tu me connais ?')

@section('content')
    <div class="fadeIn">
        <div class="center mb16">
            <span class="badge neutre" style="font-size:15px; padding:8px 16px" id="quiz-status">Partie en cours</span>
            <div class="tiny muted mt8" id="quiz-compteur"></div>
            <div id="quiz-sommaire" class="mt8"></div>
        </div>

        <div id="quiz-list" class="mt16">
            <div class="center" style="padding:40px"><div class="spinner"></div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const quizUrl = @json(url('jeux/tu-me-connais/'.$session->id));
        let stateData = null;

        function render() {
            const list = document.getElementById('quiz-list');
            const statusEl = document.getElementById('quiz-status');
            const compteur = document.getElementById('quiz-compteur');
            const sommaire = document.getElementById('quiz-sommaire');

            const drafts = [...list.querySelectorAll('input')].map(inp => ({ id: inp.id, value: inp.value }));
            const invVisible = [...list.querySelectorAll('[id^="inv-"]')].filter(el => el.style.display !== 'none').map(el => el.id);
            const focusedId = document.activeElement && list.contains(document.activeElement) ? document.activeElement.id : null;

            list.innerHTML = stateData.questions.map((q, idx) => {
                const cibleTag = `<span class="tiny muted">À propos de <b>${q.cible}</b></span>`;
                let body = '';

                if (q.resultat) {
                    body = renderResultat(q);
                } else if (q.jeSuisCible) {
                    body = q.saReponse
                        ? renderJugement(q)
                        : `<div class="badge neutre mt8">⏳ En attente de la réponse de ${stateData.partner.name}…</div>`;
                } else if (!q.maReponse) {
                    body = `
                        <form class="flex gap8 mt8" onsubmit="event.preventDefault(); repondre(${q.id}, this)">
                            <input class="input" id="rep-${q.id}" name="reponse" maxlength="255" placeholder="Ta réponse…" required>
                            <button class="btn btn-sm btn-primary">Envoyer</button>
                        </form>`;
                } else {
                    body = `<div class="badge neutre mt8">⏳ ${q.cible} juge ta réponse…</div>`;
                }

                return `
                    <div class="card pad-sm">
                        <div class="flex between items-center mb8">
                            ${cibleTag}
                            <span class="tiny muted">Q${idx + 1}</span>
                        </div>
                        <div style="font-size:15px; line-height:1.5">${esc(q.texte)}</div>
                        ${body}
                    </div>`;
            }).join('') || '<div class="center muted">Chargement des questions…</div>';

            compteur.textContent = stateData.status === 'terminee'
                ? 'Tous les jugements sont rendus !'
                : `Toi ${stateData.mesReponses} / ${stateData.aRepondre} · ${stateData.partner.name} ${stateData.sesReponses} / ${stateData.aRepondre}`;

            statusEl.className = stateData.status === 'terminee'
                ? 'badge succes'
                : (stateData.mesReponses >= stateData.aRepondre ? 'badge neutral' : 'badge neutre');
            statusEl.textContent = stateData.status === 'terminee'
                ? '🎉 Terminé'
                : (stateData.mesReponses >= stateData.aRepondre ? '⏳ Tu attends le/la partenaire' : 'Réponds en secret');

            if (stateData.status === 'terminee') {
                const connus = stateData.questions.filter(q => q.resultat === 'match' && !q.jeSuisCible).length;
                sommaire.innerHTML = `<div class="card mt16">
                    <div class="center">
                        <div style="font-size:22px">🏆</div>
                        <h2 style="font-size:18px">Tu connais ${stateData.partner.name} : ${connus} / 4</h2>
                        <p class="muted tiny">${connus >= 3 ? 'Impossible de te mentir !' : connus === 2 ? 'Pas mal, continue à observer…' : 'Il reste de la découverte à faire 😏'}</p>
                        <a href="{{ route('quiz.index') }}" class="btn btn-sm btn-primary mt8">Rejouer</a>
                    </div>
                </div>`;
            } else {
                sommaire.innerHTML = '';
            }

            drafts.forEach(d => {
                const el = document.getElementById(d.id);
                if (el) el.value = d.value;
            });
            invVisible.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            });
            if (focusedId) {
                const el = document.getElementById(focusedId);
                if (el) {
                    el.focus();
                    const len = el.value.length;
                    if (el.setSelectionRange) el.setSelectionRange(len, len);
                }
            }
        }

        function renderJugement(q) {
            return `
                <div class="chip mt8">🧠 ${stateData.partner.name} a répondu : <b style="margin-left:4px">${esc(q.saReponse)}</b></div>
                <div class="small muted mt8">Dis si c'est vrai ou faux :</div>
                <div class="flex gap8 mt8" style="flex-wrap:wrap">
                    <button class="btn btn-sm btn-primary" onclick="juger(${q.id}, true)">✅ C'est vrai</button>
                    <button class="btn btn-sm btn-danger" onclick="document.getElementById('inv-${q.id}').style.display='flex'">❌ C'est faux</button>
                </div>
                <div id="inv-${q.id}" style="display:none" class="flex gap8 mt8">
                    <input class="input" id="br-${q.id}" maxlength="255" placeholder="La vraie réponse…">
                    <button class="btn btn-sm btn-danger" onclick="juger(${q.id}, false)">Valider le faux</button>
                </div>`;
        }

        function renderResultat(q) {
            const chip = q.jeSuisCible
                ? `<span class="chip">Réponse de ${stateData.partner.name} : <b style="margin-left:4px">${esc(q.saReponse)}</b></span>`
                : `<span class="chip">Ta réponse : <b style="margin-left:4px">${esc(q.maReponse)}</b></span>`;

            let result = '';
            if (q.resultat === 'match') {
                result = q.jeSuisCible
                    ? `<div class="badge succes mt8">✅ ${stateData.partner.name} t'a connu(e) ! <b>+10 pts pour ${stateData.partner.name}</b></div>`
                    : `<div class="badge succes mt8">✅ ${q.cible} a confirmé ! C'était bien. <b>+10 pts pour toi</b></div>`;
            } else {
                const vraie = q.bonneReponse ? ` « ${esc(q.bonneReponse)} »` : ' c\'était faux.';
                result = q.jeSuisCible
                    ? `<div class="badge warning mt8" style="background:rgba(255,176,32,.14);color:var(--warning)">❌ ${stateData.partner.name} n'a pas trouvé. La vraie réponse était${vraie}</div>`
                    : `<div class="badge warning mt8" style="background:rgba(255,176,32,.14);color:var(--warning)">❌ Pas cette fois… La vraie réponse était${vraie}</div>`;
            }

            return `<div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">${chip}</div>${result}`;
        }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        async function repondre(sessionQuestionId, form) {
            const input = form.querySelector('input');
            if (!input.value.trim()) return;
            const res = await api(quizUrl + '/repondre', {
                method: 'POST',
                body: { question_id: sessionQuestionId, reponse: input.value },
            });
            if (res.ok) {
                input.value = '';
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

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(quizUrl + '/etat', (data) => { stateData = data; render(); }, { interval: 1500 });
        });
    </script>
@endpush
