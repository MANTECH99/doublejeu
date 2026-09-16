@extends('layouts.app')

@section('title', 'Quoridor à deux')

@push('head')
    <style>
        .q-modes { display:flex; gap:10px; margin-bottom:14px; }
        .q-mode { flex:1; cursor:pointer; }
        .q-mode input { position:absolute; opacity:0; }
        .q-mode-card {
            display:block; border:2px solid var(--border); border-radius:12px; padding:12px;
            text-align:center; transition:border-color .15s, box-shadow .15s;
        }
        .q-mode-card b { display:block; font-size:14px; margin-bottom:4px; }
        .q-mode-card small { display:block; color:var(--text-2); font-size:12px; line-height:1.35; }
        .q-mode input:checked + .q-mode-card { border-color:var(--primary-2); box-shadow:0 0 0 2px rgba(90,180,160,.35); }
    </style>
@endpush

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🧱</div>
            <h1 class="title">Quoridor à deux</h1>
            <p class="subtitle">Traverse le plateau avant l'autre et barre-lui la route avec tes murs…</p>
        </div>

        @if ($partie)
            <div class="card center pulse-glow" style="border-color:rgba(230,57,70,.4)">
                <h2>Une partie est en cours 🧱</h2>
                <p class="muted">
                    @if ($partie->tour)
                        Au tour de <b>{{ $partie->tour->name }}</b>.
                    @else
                        Partie en attente.
                    @endif
                </p>
                <a href="{{ route('quoridor.jouer', $partie) }}" class="btn btn-primary btn-block">Reprendre la partie</a>
            </div>
        @else
            <div class="card mt16">
                <h2>Lancer une partie</h2>
                <p class="muted mb16">À tour de rôle : avance ton pion d'une case, ou pose un mur pour bloquer le passage. Chacun a 10 murs.</p>
                <form method="POST" action="{{ route('quoridor.start') }}">
                    @csrf
                    <div class="q-modes">
                        <label class="q-mode">
                            <input type="radio" name="mode" value="classique" checked>
                            <span class="q-mode-card">
                                <b>Face à face</b>
                                <small>Un pion en haut, un en bas. Chacun vise la ligne opposée.</small>
                            </span>
                        </label>
                        <label class="q-mode">
                            <input type="radio" name="mode" value="course">
                            <span class="q-mode-card">
                                <b>Course</b>
                                <small>Les deux partent en bas, la ligne d'arrivée est en haut.</small>
                            </span>
                        </label>
                    </div>
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
                        <a href="{{ route('quoridor.jouer', $p) }}" class="btn btn-sm btn-ghost">Rouvrir 🔍</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection