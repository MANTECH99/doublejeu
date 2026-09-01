@extends('layouts.app')

@section('title', 'Lier ton couple')

@section('content')
    <div class="center" style="padding-top: 10px">
        <div style="font-size:54px; margin-bottom:6px">💞</div>
        <h1 class="title">Crée ton couple</h1>
        <p class="subtitle" style="max-width:340px;margin:0 auto 24px">
            Double Jeu se joue à deux. Génère un code et envoie-le à ton/ta partenaire, ou entre le sien.
        </p>
    </div>

    @if ($couple && $couple->code_unique && ! $couple->isLinked())
        <div class="card center pop">
            <h2>Ton code de couple</h2>
            <div style="font-size:38px; font-weight:800; letter-spacing:6px; color:var(--primary-2); margin:14px 0">
                {{ $couple->code_unique }}
            </div>
            <p class="muted">Envoie ce code à l'être cher. Unique et personnel.</p>
            <button class="btn btn-ghost btn-sm" onclick="copyCode(this)" data-code="{{ $couple->code_unique }}">
                📋 Copier le code
            </button>
        </div>
    @else
        <div class="card center">
            <h2>1. Génère ton code</h2>
            <p class="muted mb16">Clique pour créer un code personnel pour toi et ton/ta partenaire.</p>
            <form method="POST" action="{{ route('couple.generate') }}">
                @csrf
                <button class="btn btn-primary btn-block">Générer mon code</button>
            </form>
        </div>
    @endif

    <div class="divider"></div>

    <div class="card">
        <h2>2. Ou entre le code de l'autre</h2>
        <p class="muted mb16">Ton/ta partenaire a généré un code ? Saisis-le ici pour lier vos comptes.</p>
        <form method="POST" action="{{ route('couple.link') }}">
            @csrf
            <label class="label" for="code">Code de couple</label>
            <input class="input" id="code" name="code" placeholder="DJ-XXXXX" required
                   style="text-transform:uppercase; letter-spacing:2px; font-weight:700">
            <button class="btn btn-soft btn-block mt16">Lier mon couple</button>
        </form>
    </div>

    <p class="muted center mt16">
        Les deux comptes doivent être liés pour jouer ensemble. 🔒
    </p>
@endsection

@push('scripts')
    <script>
        function copyCode(btn) {
            const code = btn.dataset.code;
            (navigator.clipboard?.writeText(code) || Promise.reject()).then(() => {
                toast('Code copié !', 'success');
            }).catch(() => {
                toast('Code : ' + code, 'info');
            });
        }
    </script>
@endpush