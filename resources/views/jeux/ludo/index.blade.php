@extends('layouts.app')

@section('title', 'Ludo à deux')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🎲</div>
            <h1 class="title">Ludo à deux</h1>
            <p class="subtitle">La course des pions version couple. Sors avec un 6, mange le pion de l'autre…</p>
        </div>

        @if ($partie)
            <div class="card center pulse-glow" style="border-color:rgba(230,57,70,.4)">
                <h2>Une partie est en cours 🎲</h2>
                <p class="muted">
                    @if ($partie->tour)
                        Au tour de <b>{{ $partie->tour->name }}</b>.
                    @else
                        Partie en attente.
                    @endif
                </p>
                <a href="{{ route('ludo.jouer', $partie) }}" class="btn btn-primary btn-block">Reprendre la partie</a>
            </div>
        @else
            <div class="card mt16">
                <h2>Lancer une partie</h2>
                <p class="muted mb16">À tour de rôle : lance le dé, déplace un pion. Règles simples et (un peu) stratégiques.</p>
                <form method="POST" action="{{ route('ludo.start') }}">
                    @csrf
                    <button class="btn btn-primary btn-block">Lancer une partie</button>
                </form>
            </div>
        @endif

        @if ($historique->count())
            <section class="section-head"><h2>Historique</h2></section>
            @foreach ($historique as $p)
                <div class="card pad-sm">
                    <div class="flex between items-center">
                        <div class="grow">
                            <b style="color:rgba(230,57,70,.9)">● {{ $p->joueur1->name }}</b> vs
                            <b style="color:rgba(52,152,219,.9)">● {{ $p->joueur2->name }}</b>
                            <div class="tiny muted">
                                {{ $p->created_at->format('d/m/Y H:i') }}
                                @if ($p->vainqueur)
                                    · 🏆 {{ $p->vainqueur->name }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('ludo.jouer', $p) }}" class="btn btn-sm btn-ghost">Rouvrir 🔍</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection