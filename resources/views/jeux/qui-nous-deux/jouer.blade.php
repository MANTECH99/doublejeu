@extends('layouts.app')

@section('title', 'Qui de nous deux ?')

@section('content')
    @php $me = auth()->user(); $partenaire = $partie->couple->partnerOf($me); $nb = $partie->partieQuestions()->count(); @endphp
    <div class="fadeIn">
        <div class="center mb16">
            <span class="badge neutre" style="font-size:15px; padding:8px 16px" id="partie-status">Partie en cours</span>
            <div class="tiny muted mt8" id="reponses-compteur">Réponses : toi 0 / {{ $nb }} · {{ $partenaire->name }} 0 / {{ $nb }}</div>
        </div>

        <div id="questions-list" class="mt16">
            <div class="center" style="padding:40px"><div class="spinner"></div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const partieUrl = @json(url('jeux/qui-nous-deux/'.$partie->id));
        const partnerName = @json($partenaire->name);
        let stateData = null;

        const CAT_LABELS = {
            personnalite: '🎭 Personnalité',
            vie_quotidienne: '🏠 Quotidien',
            relation: '💞 Relation',
            habitudes: '☕ Habitudes',
        };

        function designationLabel(des, moiCible, saCible) {
            return des === 'moi' ? 'toi' : partnerName;
        }

        function render() {
            const list = document.getElementById('questions-list');
            const statusEl = document.getElementById('partie-status');
            const compteur = document.getElementById('reponses-compteur');

            compteur.textContent = stateData.status === 'terminee'
                ? `🎉 Partie terminée — Score : toi ${stateData.mesPoints} pts · ${partnerName} ${stateData.sesPoints} pts`
                : `Réponses : toi ${stateData.mesReponses} / ${stateData.nbQuestions} · ${partnerName} ${stateData.sesReponses} / ${stateData.nbQuestions} · Score : ${stateData.mesPoints} pts`;

            statusEl.className = stateData.status === 'terminee'
                ? 'badge succes'
                : (stateData.mesReponses === stateData.nbQuestions ? 'badge neutral' : 'badge neutre');
            statusEl.textContent = stateData.status === 'terminee'
                ? '🎉 Toutes les réponses sont révélées'
                : (stateData.mesReponses === stateData.nbQuestions ? '⏳ Tu attends {{ $partenaire->name }}' : 'Réponds en secret');

            list.innerHTML = stateData.questions.map((q, idx) => {
                const cat = (CAT_LABELS[q.categorie] || '');
                let resultBox = '';
                let actions = '';

                if (!q.revelee) {
                    actions = q.maDesignation
                        ? `<div class="tiny muted">✅ Tu as désigné <b>${q.maDesignation === 'moi' ? 'toi' : partnerName}</b> · en attente du/de la partenaire</div>`
                        : `
                        <div class="grid2 mt8">
                            <button class="btn btn-ghost" onclick="repondre(${q.id}, 'moi')">🙋 Moi</button>
                            <button class="btn btn-primary" onclick="repondre(${q.id}, 'partenaire')">💞 ${partnerName}</button>
                        </div>`;
                } else {
                    if (q.resultat === 'accord') {
                        resultBox = `
                            <div class="badge succes mt8">✅ Accord ! +5 pts chacun</div>
                            <div class="tiny muted mt8">Vous avez tous les deux choisi « ${q.maCible || q.saCible} » : ça colle !</div>`;
                    } else {
                        resultBox = `
                            <div class="badge warning" style="background:rgba(255,176,32,.14);color:var(--warning)">⚡ Mode débat !</div>
                            <div class="tiny mt8" style="font-style:italic">
                                <b>Toi</b> → ${q.maCible} · <b>${partnerName}</b> → ${q.saCible}
                            </div>
                            ${q.debat_resolu
                                ? `<div class="tiny mt8" style="color:var(--success)">✨ On s'est expliqués</div>`
                                : `<button class="btn btn-sm btn-soft mt8" onclick="resoudre(${q.id})">On s'est expliqués ✓</button>`}`;
                    }
                }

                return `
                <div class="card pad-sm">
                    <div class="flex between items-center mb8">
                        <span class="tiny muted">${cat}</span>
                        <span class="tiny muted">Q${idx + 1}</span>
                    </div>
                    <div style="font-size:15px; line-height:1.5">${q.texte}</div>
                    ${q.revelee && q.maDesignation ? `
                        <div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                            <span class="chip">Toi : <b style="margin-left:4px">${q.maCible}</b></span>
                            <span class="chip">${partnerName} : <b style="margin-left:4px">${q.saCible}</b></span>
                        </div>` : ''}
                    ${resultBox}
                    ${actions}
                </div>`;
            }).join('') || '<div class="center muted">Chargement des questions…</div>';
        }

        async function repondre(questionId, designation) {
            const res = await api(partieUrl + '/repondre', {
                method: 'POST',
                body: { question_id: questionId, designation },
            });
            if (res.ok) {
                await refresh();
            }
        }

        async function resoudre(questionId) {
            const res = await api(partieUrl + '/resoudre', {
                method: 'POST',
                body: { question_id: questionId },
            });
            if (res.ok) {
                toast('Débat résolu, bravo !', 'success');
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

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(partieUrl + '/etat', (data) => { stateData = data; render(); }, { interval: 1500 });
        });
    </script>
@endpush