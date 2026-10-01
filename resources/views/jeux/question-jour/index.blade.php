@extends('layouts.app')

@section('title', 'La Question du Jour')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🌅</div>
            <h1 class="title">La Question du Jour</h1>
            <p class="subtitle">Une question par jour pour le couple. Les réponses restent secrètes jusqu'à ce que vous ayez répondu tous les deux.</p>
        </div>

        @if ($poolEpuise)
            <div class="center">
                <p class="tiny muted" style="display:inline-block; padding:8px 12px; border-radius:8px; background:rgba(0,0,0,.04)">
                    💡 Vous avez déjà vu toutes les questions du jour — elles sont reprises au hasard, certaines peuvent se répéter.
                </p>
            </div>
        @endif

        <div class="card mt16" style="border-color:rgba(249,115,22,.45)">
            <div class="flex between items-center mb8">
                <span class="tiny muted" id="question-date">…</span>
                <span class="badge neutre" id="question-categorie"></span>
            </div>
            <div style="font-size:17px; line-height:1.55" id="question-texte">Chargement…</div>

            <div id="question-zone" class="mt16"></div>
        </div>

        @if ($historique->total())
            <section class="section-head">
                <h2>Réponses passées</h2>
                <span class="tiny muted">{{ $historique->total() }} jour(s)</span>
            </section>
            @foreach ($historique as $qj)
                @php
                    $reponses = $qj->reponses->filter(fn ($r) => filled($r->reponse))->values();
                    $revele = $reponses->count() === 2;
                @endphp
                <div class="card pad-sm">
                    <div class="flex between items-center mb8">
                        <span class="tiny muted">{{ $qj->jour->format('d/m/Y') }}</span>
                        <span class="badge neutre">{{ $qj->question->categorie === 'profonde' ? '🌙 Profonde' : '😂 Drôle' }}</span>
                    </div>
                    <div style="font-size:15px; line-height:1.5">{{ $qj->question->texte }}</div>
                    @if ($revele)
                        <div class="qj-reponses">
                            @foreach ($reponses as $r)
                                <div class="qj-reponse">
                                    <div class="qj-reponse-nom">{{ $r->joueur->name }}</div>
                                    <div class="qj-reponse-texte txt-libre">{{ $r->reponse }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt8"><span class="badge neutre">🔒 Réponses jamais révélées</span></div>
                    @endif
                </div>
            @endforeach

            @if ($historique->hasPages())
                <nav class="flex between items-center mt16">
                    @if ($historique->onFirstPage())
                        <span class="btn btn-sm btn-ghost" aria-hidden="true" style="opacity:.4">← Plus récent</span>
                    @else
                        <a class="btn btn-sm btn-ghost" href="{{ $historique->previousPageUrl() }}">← Plus récent</a>
                    @endif
                    <span class="tiny muted">Page {{ $historique->currentPage() }} / {{ $historique->lastPage() }}</span>
                    @if ($historique->hasMorePages())
                        <a class="btn btn-sm btn-ghost" href="{{ $historique->nextPageUrl() }}">Plus ancien →</a>
                    @else
                        <span class="btn btn-sm btn-ghost" aria-hidden="true" style="opacity:.4">Plus ancien →</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const questionUrl = @json(url('jeux/question-du-jour'));
        const CAT_LABELS = { profonde: '🌙 Profonde', drole: '😂 Drôle' };

        function renderQuestion(data) {
            const dateEl = document.getElementById('question-date');
            const catEl = document.getElementById('question-categorie');
            const texteEl = document.getElementById('question-texte');
            const zone = document.getElementById('question-zone');

            const draft = zone.querySelector('#reponse-question')?.value ?? '';
            const wasFocused = document.activeElement && zone.contains(document.activeElement);

            dateEl.textContent = '📅 ' + data.date;
            catEl.textContent = CAT_LABELS[data.categorie] || data.categorie;
            texteEl.textContent = data.texte;

            if (!data.jaiRepondu) {
                zone.innerHTML = `
                    <textarea class="input" id="reponse-question" rows="3" maxlength="1500" placeholder="Ta réponse, en secret…"></textarea>
                    <button class="btn btn-primary btn-block mt8" onclick="repondreQuestion()">Répondre 🤫</button>`;
            } else if (!data.revelee) {
                zone.innerHTML = `
                    <div class="badge neutral mt8">✅ Réponse enregistrée</div>
                    <div class="tiny muted mt8">${data.ilElleARepondu ? data.partenaire : 'Ton/ta partenaire'} ${data.ilElleARepondu ? 'a répondu… attends le verdict' : 'n\'a pas encore répondu. Réponse révélée pour tous les deux !'}</div>`;
            } else {
                zone.innerHTML = `
                    <div class="badge succes mt8">🔓 Réponses révélées !</div>
                    <div class="qj-reponses">
                        <div class="qj-reponse">
                            <div class="qj-reponse-nom">Toi</div>
                            <div class="qj-reponse-texte txt-libre"></div>
                        </div>
                        <div class="qj-reponse">
                            <div class="qj-reponse-nom"></div>
                            <div class="qj-reponse-texte txt-libre"></div>
                        </div>
                    </div>
                    <div class="tiny muted mt8">À demain pour une nouvelle question 🤍</div>`;
            }

            // Remplissage en textContent : jamais d'injection, et les paragraphes
            // sont restitués tels quels grâce à .txt-libre (white-space: pre-wrap).
            const revealed = zone.querySelectorAll('.qj-reponse');
            if (revealed.length === 2) {
                revealed[0].querySelector('.qj-reponse-texte').textContent = data.maReponse ?? '';
                revealed[1].querySelector('.qj-reponse-nom').textContent = data.partenaire ?? '';
                revealed[1].querySelector('.qj-reponse-texte').textContent = data.saReponse ?? '';
            }

            const textarea = document.getElementById('reponse-question');
            if (textarea) {
                textarea.value = draft;
                if (wasFocused) textarea.focus();
            }
        }

        async function repondreQuestion() {
            const input = document.getElementById('reponse-question');
            if (!input || !input.value.trim()) return;
            const res = await api(questionUrl + '/repondre', {
                method: 'POST',
                body: { reponse: input.value },
            });
            if (res.ok) {
                toast('Réponse envoyée en secret !', 'success');
                refresh();
            }
        }

        async function refresh() {
            const res = await api(questionUrl + '/etat', { json: false });
            if (res.ok) renderQuestion(res.data);
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(questionUrl + '/etat', renderQuestion, { interval: 1800 });
        });
    </script>
@endpush