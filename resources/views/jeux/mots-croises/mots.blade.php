@extends('layouts.app')

@section('title', 'Mes mots croisés')

@section('content')
    @php $partenaire = $couple->partnerOf(auth()->user()); @endphp
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:46px; margin-bottom:4px">🧳</div>
            <h1 class="title">Mes mots</h1>
            <p class="subtitle">Chacun invente ses mots et ses indices, en secret. C'est avec eux que tu créeras une grille rien que pour {{ $partenaire->name }}.</p>
        </div>

        @if ($errors->any())
            <div class="card pad-sm" style="border-color:var(--danger,#ef4444); color:var(--danger,#ef4444)">
                @foreach ($errors->all() as $e)
                    <div class="tiny">⚠️ {{ $e }}</div>
                @endforeach
            </div>
        @endif

        <section class="section-head"><h2>Ajouter un mot</h2><span class="tiny muted">4 à 10 lettres, sans accents ni espaces</span></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('mots-croises.mots.creer') }}" class="mb16">
                @csrf
                <label class="label">Nouveau mot + son indice</label>
                <div class="grid2">
                    <input class="input" name="mot" maxlength="10" placeholder="Ton mot (ex : MONTAGNE)" style="text-transform:uppercase" value="{{ old('mot') }}" required>
                    <input class="input" name="indice" maxlength="200" placeholder="L'indice pour {{ $partenaire->name }} (ex : notre premier grand départ)" value="{{ old('indice') }}" required>
                </div>
                <button class="btn btn-sm btn-soft btn-block mt8">Ajouter à ma grille</button>
            </form>
            <div class="tiny muted">🔒 Ta liste est <b>privée</b> : {{ $partenaire->name }} ne peut pas la voir. Ce sont tes mots, et eux seuls remplissent ta grille.</div>
        </div>

        <section class="section-head"><h2>Mes mots</h2><span class="tiny muted">{{ count($mesMots) }} / 3+ mots nécessaires</span></section>
        <div class="card pad-sm">
            @forelse ($mesMots as $c)
                <div class="row">
                    <span class="chip" style="min-width:auto; padding:2px 10px; font-weight:800; letter-spacing:.5px">{{ $c->mot }}</span>
                    <div class="grow" style="font-size:14px">{{ $c->indice }}</div>
                    <form method="POST" action="{{ route('mots-croises.mots.detruire', $c->id) }}">
                        @csrf @method('DELETE')
                        <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                    </form>
                </div>
            @empty
                <div class="center muted" style="padding:20px">Aucun mot pour l'instant. Ajoute-en au moins 3 ci-dessus !</div>
            @endforelse

            <div class="center mt16">
                <a class="btn btn-sm btn-primary btn-block" href="{{ route('mots-croises.index') }}">
                    🧩 Aller générer la grille pour {{ $partenaire->name }}
                </a>
            </div>
        </div>

        <div class="center mt16">
            <a class="btn btn-sm" href="{{ route('mots-croises.index') }}">← Retour à la grille</a>
        </div>
    </div>
@endsection