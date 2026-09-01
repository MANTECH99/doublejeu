@extends('layouts.app')

@section('title', 'Mes cartes')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:46px; margin-bottom:4px">🃏</div>
            <h1 class="title">Mes cartes</h1>
            <p class="subtitle">Ajoute tes propres cartes, défis et questions. Ils seront mélangés à la banque.</p>
        </div>

        {{-- Vérités --}}
        <section class="section-head"><h2>Cartes Vérité</h2></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('cartes.verite') }}" class="mb16">
                @csrf
                <label class="label">Nouvelle Vérité</label>
                <div class="grid2">
                    <input class="input" name="texte" placeholder="Écris ta question…" required>
                    <select class="select" name="niveau">
                        <option value="doux">🍑 Doux</option>
                        <option value="chaud">🔥 Chaud</option>
                        <option value="brulant">🌶️ Brûlant</option>
                    </select>
                </div>
                <button class="btn btn-sm btn-soft btn-block mt8">Ajouter la Vérité</button>
            </form>
            @foreach ($verites as $c)
                <div class="row">
                    <span class="badge {{ $c->niveau }}">{{ $c->niveau }}</span>
                    <div class="grow" style="font-size:14px">{{ $c->texte }}</div>
                    @if ($c->created_by && $c->created_by === auth()->id())
                        <form method="POST" action="{{ route('cartes.detruire', ['type' => 'verite', 'id' => $c->id]) }}">
                            @csrf @method('DELETE')
                            <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <section class="section-head"><h2>Cartes Action</h2></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('cartes.action') }}" class="mb16">
                @csrf
                <label class="label">Nouvelle Action</label>
                <div class="grid2">
                    <input class="input" name="texte" placeholder="Le défi à réaliser…" required>
                    <select class="select" name="niveau">
                        <option value="doux">🍑 Doux</option>
                        <option value="chaud">🔥 Chaud</option>
                        <option value="brulant">🌶️ Brûlant</option>
                    </select>
                </div>
                <button class="btn btn-sm btn-soft btn-block mt8">Ajouter l'Action</button>
            </form>
            @foreach ($actions as $c)
                <div class="row">
                    <span class="badge {{ $c->niveau }}">{{ $c->niveau }}</span>
                    <div class="grow" style="font-size:14px">{{ $c->texte }}</div>
                    @if ($c->created_by && $c->created_by === auth()->id())
                        <form method="POST" action="{{ route('cartes.detruire', ['type' => 'action', 'id' => $c->id]) }}">
                            @csrf @method('DELETE')
                            <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Défis enveloppes --}}
        <section class="section-head"><h2>Défis enveloppes</h2></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('cartes.defi') }}" class="mb16">
                @csrf
                <label class="label">Nouveau défi d'enveloppe</label>
                <div class="grid2">
                    <input class="input" name="texte" placeholder="Le défi…" required>
                    <select class="select" name="couleur">
                        <option value="rouge">🔥 Rouge (Osé)</option>
                        <option value="bleue">💙 Bleue (Tendre)</option>
                        <option value="verte">💚 Verte (Drôle)</option>
                    </select>
                </div>
                <button class="btn btn-sm btn-ghost btn-block mt8">Ajouter le défi</button>
            </form>
            @foreach ($defis as $c)
                <div class="row">
                    <span class="badge {{ $c->couleur }}">{{ $c->couleur }}</span>
                    <div class="grow" style="font-size:14px">{{ $c->texte }}</div>
                    @if ($c->created_by && $c->created_by === auth()->id())
                        <form method="POST" action="{{ route('cartes.detruire', ['type' => 'defi', 'id' => $c->id]) }}">
                            @csrf @method('DELETE')
                            <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Questions Oui/Non --}}
        <section class="section-head"><h2>Questions Oui/Non</h2></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('cartes.question') }}" class="mb16">
                @csrf
                <label class="label">Nouvelle question Oui/Non</label>
                <div class="grid2">
                    <input class="input" name="texte" placeholder="Accepterais-tu de…" required>
                    <select class="select" name="categorie">
                        <option value="vie_quotidienne">🏠 Vie quotidienne</option>
                        <option value="intimite">❤️ Intimité</option>
                        <option value="fantasmes">🔥 Fantasmes</option>
                        <option value="aventure">🧭 Aventure</option>
                    </select>
                </div>
                <button class="btn btn-sm btn-ghost btn-block mt8">Ajouter la question</button>
            </form>
            @foreach ($questions as $c)
                <div class="row">
                    <span class="badge neutre">{{ $c->categorie }}</span>
                    <div class="grow" style="font-size:14px">{{ $c->texte }}</div>
                    @if ($c->created_by && $c->created_by === auth()->id())
                        <form method="POST" action="{{ route('cartes.detruire', ['type' => 'question', 'id' => $c->id]) }}">
                            @csrf @method('DELETE')
                            <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Gages --}}
        <section class="section-head"><h2>Gages</h2></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('cartes.gage') }}" class="mb16">
                @csrf
                <label class="label">Nouveau gage</label>
                <div class="grid2">
                    <input class="input" name="texte" placeholder="Le gage à imposer…" required>
                    <button class="btn btn-sm btn-ghost">Ajouter le gage</button>
                </div>
            </form>
            @foreach ($gages as $c)
                <div class="row">
                    <span class="badge neutre">😅</span>
                    <div class="grow" style="font-size:14px">{{ $c->texte }}</div>
                    @if ($c->created_by && $c->created_by === auth()->id())
                        <form method="POST" action="{{ route('cartes.detruire', ['type' => 'gage', 'id' => $c->id]) }}">
                            @csrf @method('DELETE')
                            <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection