@extends('layouts.app')

@section('title', 'Qui de nous deux ?')

@section('content')
    @php $partenaire = $couple->partnerOf(auth()->user()); @endphp
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🙋</div>
            <h1 class="title">Qui de nous deux ?</h1>
            <p class="subtitle">{{ \App\Http\Controllers\QuiDeNousDeuxController::NB_QUESTIONS }} questions secrètes. Choisis « moi » ou « {{ $partenaire->name }} »… et tope si tu devines la même personne que {{ $partenaire->name }} (+5 pts chacun).</p>
        </div>

        @if ($partie)
            <div class="card center pulse-glow" style="border-color:rgba(147,51,234,.5)">
                <h2>Partie en attente 🎲</h2>
                <p class="muted">Une partie de {{ \App\Http\Controllers\QuiDeNousDeuxController::NB_QUESTIONS }} questions t'attend.</p>
                <a href="{{ route('qdn2.jouer', $partie) }}" class="btn btn-primary btn-block">Reprendre</a>
            </div>
        @else
            <div class="card mt16">
                <h2>Lancer une partie</h2>
                <p class="muted mb16">Les deux joueurs doivent être connectés en même temps. À chaque question, chacun désigne l'un de vous deux, en secret. Réponses identiques = accord, +5 points pour les deux. Réponses différentes = mode débat !</p>
                <form method="POST" action="{{ route('qdn2.start') }}">
                    @csrf
                    <button class="btn btn-primary btn-block">Tirer {{ \App\Http\Controllers\QuiDeNousDeuxController::NB_QUESTIONS }} questions</button>
                </form>
            </div>
        @endif

        <section class="section-head">
            <h2>Mes questions</h2>
            <a href="{{ route('qdn2.questions') }}" class="tiny">ajouter →</a>
        </section>
        <div class="card pad-sm">
            <div class="flex between items-center">
                <div class="grow">
                    <strong>{{ $nbPersoQuestions }} question(s) personnelle(s)</strong>
                    <div class="tiny muted">Elles se mélangent à la banque officielle quand tu lances une partie.</div>
                </div>
                <a href="{{ route('qdn2.questions') }}" class="btn btn-sm btn-soft">✨ Gérer</a>
            </div>
        </div>

        @if ($historique->count())
            <section class="section-head"><h2>Historique</h2></section>
            @foreach ($historique as $p)
                <div class="card pad-sm">
                    <div class="flex between items-center">
                        <div class="grow">
                            <b>{{ $p->joueur1->name }}</b> vs <b>{{ $p->joueur2->name }}</b>
                            <div class="tiny muted">{{ $p->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <a href="{{ route('qdn2.jouer', $p) }}" class="btn btn-sm btn-ghost">Rouvrir 🔍</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection