@extends('layouts.app')

@section('title', 'Mes questions · Qui de nous deux ?')

@section('content')
    @php $partenaire = $couple->partnerOf(auth()->user()); @endphp
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:46px; margin-bottom:4px">✨</div>
            <h1 class="title">Mes questions</h1>
            <p class="subtitle">Invente des questions « Qui de nous deux est le plus… ? ». Elles rejoignent la banque officielle lors des tirages.</p>
        </div>

        @if ($errors->any())
            <div class="card pad-sm" style="border-color:var(--danger,#ef4444); color:var(--danger,#ef4444)">
                @foreach ($errors->all() as $e)
                    <div class="tiny">⚠️ {{ $e }}</div>
                @endforeach
            </div>
        @endif

        <section class="section-head"><h2>Ajouter une question</h2><span class="tiny muted">Pose-la comme une devinette sur vous deux</span></section>
        <div class="card pad-sm">
            <form method="POST" action="{{ route('qdn2.questions.creer') }}" class="mb16">
                @csrf
                <label class="label">Ta question</label>
                <input class="input" name="texte" maxlength="300" placeholder="Qui de nous deux est le plus… ?" value="{{ old('texte') }}" required>
                <div class="grid2 mt8">
                    <select class="input" name="categorie" required>
                        <option value="personnalite">🎭 Personnalité</option>
                        <option value="vie_quotidienne">🏠 Vie quotidienne</option>
                        <option value="relation">💞 Relation</option>
                        <option value="habitudes">☕ Habitudes</option>
                    </select>
                    <button class="btn btn-sm btn-soft btn-block">Ajouter</button>
                </div>
            </form>
            <div class="tiny muted">🔒 Ta banque perso est <b>privée</b> : {{ $partenaire->name }} ne voit pas tes questions dans l'app.</div>
        </div>

        <section class="section-head"><h2>Mes questions</h2><span class="tiny muted">{{ count($mesQuestions) }} enregistrée(s)</span></section>
        <div class="card pad-sm">
            @forelse ($mesQuestions as $q)
                <div class="row">
                    <div class="grow" style="font-size:14px">{{ $q->texte }}</div>
                    <span class="tiny muted" style="white-space:nowrap">{{ $q->categorie }}</span>
                    <form method="POST" action="{{ route('qdn2.questions.detruire', $q->id) }}">
                        @csrf @method('DELETE')
                        <button class="icon-btn" style="width:32px;height:32px">🗑️</button>
                    </form>
                </div>
            @empty
                <div class="center muted" style="padding:20px">Aucune question personnelle pour l'instant. Ajoutes-en une ci-dessus !</div>
            @endforelse

            <div class="center mt16">
                <a class="btn btn-sm btn-primary btn-block" href="{{ route('qdn2.index') }}">
                    🙋 Aller lancer une partie
                </a>
            </div>
        </div>

        <div class="center mt16">
            <a class="btn btn-sm" href="{{ route('qdn2.index') }}">← Retour au jeu</a>
        </div>
    </div>
@endsection