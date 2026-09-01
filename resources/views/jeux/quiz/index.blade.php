@extends('layouts.app')

@section('title', 'Tu me connais ?')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">❓</div>
            <h1 class="title">Tu me connais ?</h1>
            <p class="subtitle">8 questions sur vous deux. Réponds à ma place… et gagne un point quand tu me connais vraiment.</p>
        </div>

        @if ($session)
            <div class="card center pulse-glow" style="border-color:rgba(234,88,12,.5)">
                <h2>Partie en attente 🎲</h2>
                <p class="muted">Une partie de {{ \App\Http\Controllers\QuizController::NB_QUESTIONS }} questions t'attend.</p>
                <a href="{{ route('quiz.jouer', $session) }}" class="btn btn-primary btn-block">
                    {{ $session->statut === 'en_cours' ? 'Reprendre' : 'Rejoindre' }}
                </a>
            </div>
        @else
            <div class="card mt16">
                <h2>Lancer une partie</h2>
                <p class="muted mb16">Chacun répond sur l'autre, la réponse est comparée en temps réel. Réponse identique = +10 points.</p>
                <button class="btn btn-primary btn-block" onclick="lancerQuiz()">Tirer {{ \App\Http\Controllers\QuizController::NB_QUESTIONS }} questions</button>
            </div>
        @endif

        @if ($historique->count())
            <section class="section-head"><h2>Historique</h2></section>
            @foreach ($historique as $s)
                <div class="card pad-sm">
                    <div class="flex between items-center">
                        <div class="grow">
                            <b>{{ $s->joueur1->name }}</b> vs <b>{{ $s->joueur2->name }}</b>
                            <div class="tiny muted">{{ $s->updated_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <a href="{{ route('quiz.jouer', $s) }}" class="btn btn-sm btn-ghost">Rouvrir 🔍</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        async function lancerQuiz() {
            const res = await api('{{ route('quiz.start') }}', { method: 'POST' });
            if (res.ok) {
                window.location.href = res.data.redirect || '{{ route('quiz.index') }}';
            }
        }
    </script>
@endpush