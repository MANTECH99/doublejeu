@extends('layouts.app')

@section('title', 'Jeu du Oui/Non')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">⚖️</div>
            <h1 class="title">Le Jeu du Oui / Non</h1>
            <p class="subtitle">10 questions secrètes. Si vous répondez tous les deux OUI, ça devient une mission.</p>
        </div>

        @if ($partie)
            <div class="card center pulse-glow" style="border-color:rgba(109,40,217,.5)">
                <h2>Partie en attente 🎲</h2>
                <p class="muted">Une partie de 10 questions t'attend.</p>
                <a href="{{ route('ouinon.jouer', $partie) }}" class="btn btn-primary btn-block">
                    {{ $partie->status === 'en_cours' ? 'Reprendre' : 'Rejoindre' }}
                </a>
            </div>
        @else
            <div class="card mt16">
                <h2>Lancer une partie</h2>
                <p class="muted mb16">Les deux joueurs doivent être connectés en même temps. Les réponses restent secrètes jusqu'à la révélation.</p>
                <form method="POST" action="{{ route('ouinon.start') }}">
                    @csrf
                    <button class="btn btn-primary btn-block">Tirer 10 questions</button>
                </form>
            </div>
        @endif

        <section class="section-head">
            <h2>Missions à réaliser</h2>
            <span class="tiny muted">{{ $missions->where('statut', 'a_realiser')->count() }} en attente</span>
        </section>

        <div class="card pad-sm">
            @forelse ($missions as $mission)
                <div class="row">
                    <div class="grow">
                        <div style="font-size:14px" class="{{ $mission->statut === 'realisee' ? 'hidden-card' : '' }}">
                            {{ $mission->question->texte }}
                        </div>
                        @if ($mission->statut === 'realisee')
                            <small style="color:var(--success)">✅ Réalisée {{ $mission->realisee_at?->diffForHumans() }} (+15 pts)</small>
                        @else
                            <small class="tiny muted">Mission à réaliser dans la semaine</small>
                        @endif
                    </div>
                    @if ($mission->statut === 'a_realiser')
                        <button class="btn btn-sm btn-soft" onclick="realiserMission({{ $mission->id }})">Je l'ai fait ✅</button>
                    @endif
                </div>
            @empty
                <div class="tiny muted center" style="padding:10px">Aucune mission validée pour l'instant.</div>
            @endforelse
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
                        <a href="{{ route('ouinon.jouer', $p) }}" class="btn btn-sm btn-ghost">Rouvrir 🔍</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        async function realiserMission(id) {
            const res = await api('/jeux/oui-non/missions/' + id + '/realiser', { method: 'POST' });
            if (res.ok) {
                toast(res.data.message, 'success');
                setTimeout(() => location.reload(), 900);
            }
        }
    </script>
@endpush