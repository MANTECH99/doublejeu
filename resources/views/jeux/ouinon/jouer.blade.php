@extends('layouts.app')

@section('title', 'Oui/Non')

@section('content')
    <div class="fadeIn">
        <div class="center mb16">
            <span class="badge neutre" style="font-size:15px; padding:8px 16px" id="partie-status">Partie en cours</span>
            <div class="tiny muted mt8" id="reponses-compteur">Réponses : toi 0 / 10 · partenaire 0 / 10</div>
        </div>

        <div id="questions-list" class="mt16">
            <div class="center" style="padding:40px"><div class="spinner"></div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const partieUrl = @json(url('jeux/oui-non/'.$partie->id));
        let stateData = null;

        const CAT_LABELS = {
            vie_quotidienne: '🏠 Quotidien',
            intimite: '❤️ Intimité',
            fantasmes: '🔥 Fantasmes',
            aventure: '🧭 Aventure',
        };

        function render() {
            const list = document.getElementById('questions-list');
            const statusEl = document.getElementById('partie-status');
            const compteur = document.getElementById('reponses-compteur');

            const drafts = [...list.querySelectorAll('input')].map(inp => ({ id: inp.id, value: inp.value }));
            const focusedId = document.activeElement && list.contains(document.activeElement) ? document.activeElement.id : null;

            compteur.textContent = stateData.status === 'terminee'
                ? 'Toutes les réponses sont révélées !'
                : `Réponses : toi ${stateData.mesReponses} / ${stateData.nbQuestions} · ${stateData.couple.p1.name === window.currentUserName ? stateData.couple.p2.name : stateData.couple.p1.name} ${stateData.sesReponses} / ${stateData.nbQuestions}`;

            statusEl.className = stateData.status === 'terminee'
                ? 'badge succes'
                : (stateData.mesReponses === stateData.nbQuestions ? 'badge neutral' : 'badge neutre');
            statusEl.textContent = stateData.status === 'terminee'
                ? '🎉 Réponses révélées'
                : (stateData.mesReponses === stateData.nbQuestions ? '⏳ Tu attends le/la partenaire' : 'Réponds en secret');

            list.innerHTML = stateData.questions.map((q, idx) => {
                const cat = (CAT_LABELS[q.categorie] || '');
                let resultBox = '';
                let actions = '';

                if (!q.revetee) {
                    const answered = !!q.maReponse;
                    actions = answered
                        ? `<div class="tiny muted">✅ Tu as répondu <b>${q.maReponse.toUpperCase()}</b> · en attente du/de la partenaire</div>`
                        : `
                        <div class="grid2 mt8">
                            <button class="btn btn-ghost" onclick="repondre(${q.id}, 'non')">🙅 NON</button>
                            <button class="btn btn-primary" onclick="repondre(${q.id}, 'oui')">💚 OUI</button>
                        </div>`;
                } else {
                    if (q.resultat === 'double_oui') {
                        resultBox = `<div class="badge succes mt8">✅ Double OUI → mission ajoutée !</div>`;
                    } else if (q.resultat === 'double_non') {
                        resultBox = `<div class="badge neutre mt8">✖️ Double NON → question écartée</div>`;
                    } else {
                        const oui = q.maReponse === 'oui' ? 'toi' : 'le/la partenaire';
                        const demande = q.maReponse === 'non';
                        resultBox = `
                            <div class="badge warning" style="background:rgba(255,176,32,.14);color:var(--warning)">⚡ Réponses différentes → le/la joueuse OUI peut demander une explication</div>
                            ${q.maExplication ? `<div class="tiny mt8" style="font-style:italic">Ta réponse : « ${q.maExplication} »</div>` : ''}
                            ${q.explication ? `<div class="tiny muted mt8" style="font-style:italic">« ${q.explication} »</div>` : ''}
                            ${stateData.status === 'terminee' && demande && !q.maExplication
                                ? `<form class="flex gap8 mt8" onsubmit="event.preventDefault(); expliquer(${q.id}, this)">
                                    <input class="input" id="exp-${q.id}" name="exp" placeholder="Explique ton NON…" required>
                                    <button class="btn btn-sm btn-soft">Envoyer</button>
                                  </form>`
                                : ''}
                        `;
                    }
                }

                return `
                <div class="card pad-sm">
                    <div class="flex between items-center mb8">
                        <span class="tiny muted">${cat}</span>
                        <span class="tiny muted">Q${idx + 1}</span>
                    </div>
                    <div style="font-size:15px; line-height:1.5">${q.texte}</div>
                    ${resultBox}
                    <div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                        ${q.maReponse ? `<span class="chip">Ta réponse : <b style="margin-left:4px">${q.maReponse.toUpperCase()}</b></span>` : ''}
                        ${q.revetee && q.saReponse ? `<span class="chip">Partenaire : <b style="margin-left:4px">${q.saReponse.toUpperCase()}</b></span>` : ''}
                    </div>
                    ${actions}
                </div>`;
            }).join('') || '<div class="center muted">Chargement des questions…</div>';

            drafts.forEach(d => {
                const el = document.getElementById(d.id);
                if (el) el.value = d.value;
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

        async function repondre(questionId, reponse) {
            const res = await api(partieUrl + '/repondre', {
                method: 'POST',
                body: { question_id: questionId, reponse },
            });
            if (res.ok) {
                await refresh();
            }
        }

        async function expliquer(questionId, form) {
            const input = form.querySelector('input');
            const res = await api(partieUrl + '/expliquer', {
                method: 'POST',
                body: { question_id: questionId, explication: input.value },
            });
            if (res.ok) {
                toast('Explication envoyée !', 'success');
                await refresh();
            }
        }

        async function refresh() {
            const res = await api(partieUrl + '/etat', { json: false });
            if (res.ok) {
                stateData = res.data;
                render();
            }
        }

        window.currentUserName = @json(auth()->user()->name);
        document.addEventListener('DOMContentLoaded', () => {
            startPolling(partieUrl + '/etat', (data) => { stateData = data; render(); }, { interval: 1500 });
        });
    </script>
@endpush