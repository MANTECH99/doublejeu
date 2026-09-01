@extends('layouts.app')

@section('title', 'Vérité ou Action')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🎭</div>
            <h1 class="title">Vérité ou Action</h1>
            <p class="subtitle">Tour à tour, choisis un niveau et révèle tes secrets.</p>
        </div>

        @if ($partie)
            <div class="card center pulse-glow" style="border-color:rgba(230,57,70,.5)">
                <h2>Partie en cours 🔥</h2>
                <p class="muted">
                    Niveau <strong class="badge {{ $partie->niveau }}">{{ $partie->niveau }}</strong> ·
                    C'est au tour de <strong>{{ $partie->joueurActif->name }}</strong>
                </p>
                <a href="{{ route('vo.jouer', $partie) }}" class="btn btn-primary btn-block">Reprendre la partie</a>
            </div>
        @endif

        <div class="card mt16">
            <h2>Commencer une partie</h2>
            <p class="muted mb16">Choisissez ensemble le niveau d'intensité. La partie démarre au hasard.</p>

            <form id="form-start" method="POST" action="{{ route('vo.start') }}">
                @csrf
                <input type="hidden" name="niveau" id="niveau" value="doux">

                <label class="label">Niveau d'intensité</label>
                <div class="pill-intensite mb16">
                    <label data-v="doux"><input type="radio" name="niveau_visuel" value="doux" checked onchange="setNiveau('doux')">🍑 Doux</label>
                    <label data-v="chaud"><input type="radio" name="niveau_visuel" value="chaud" onchange="setNiveau('chaud')">🔥 Chaud</label>
                    <label data-v="brulant"><input type="radio" name="niveau_visuel" value="brulant" onchange="setNiveau('brulant')">🌶️ Brûlant</label>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Commencer à jouer</button>
            </form>
        </div>

        <section class="section-head"><h2>Règles rapides</h2></section>
        <div class="card pad-sm" style="font-size:14px">
            <div class="row"><span>✅</span><div class="grow">Vérité acceptée = <b>+10 pts</b>, Action = <b>+20 pts</b></div></div>
            <div class="row"><span>🙅</span><div class="grow">Refus = <b>−5 pts</b> et ton/ta partenaire gagne <b>+5 pts</b> + un gage</div></div>
            <div class="row"><span>🔄</span><div class="grow">Ton/ta partenaire valide la réalisation du défi</div></div>
            <div class="row" style="border-bottom:none"><span>🏁</span><div class="grow">Le score est visible en temps réel</div></div>
        </div>

        @if ($historique->count())
            <section class="section-head"><h2>Historique</h2></section>
            @foreach ($historique as $p)
                <div class="card pad-sm">
                    <div class="flex between items-center">
                        <span class="badge {{ $p->niveau }}">{{ $p->niveau }}</span>
                        <span class="tiny muted">{{ $p->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="grid2 mt8">
                        <div class="tiny"><b>{{ $p->couple->user1->name }}</b> : {{ $p->score_joueur1 }} pts</div>
                        <div class="tiny"><b>{{ $p->couple->user2->name }}</b> : {{ $p->score_joueur2 }} pts</div>
                    </div>
                    <div class="tiny muted mt8">{{ $p->tours->count() }} tour(s) · {{ $p->status === 'terminee' ? 'terminée' : 'abandonnée' }}</div>
                </div>
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        function setNiveau(v) {
            document.getElementById('niveau').value = v;
        }
    </script>
@endpush