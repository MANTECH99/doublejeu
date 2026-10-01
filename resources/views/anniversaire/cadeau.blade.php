@extends('layouts.app')

@section('title', 'Joyeux anniversaire')

@section('content')
    <div class="fadeIn">
        <div class="confettis" id="confettis" aria-hidden="true"></div>

        <div class="center">
            <div style="font-size:52px; margin-bottom:4px">🎂</div>
            <h1 class="title">Joyeux anniversaire {{ $me->name }} !</h1>
            <p class="subtitle">{{ $partner->name }} t'a préparé quelque chose pour toi 💝</p>
        </div>

        <div class="card cel-cadeau">
            <div class="cel-cadeau-auteur">
                <x-avatar :user="$partner" class="sm" />
                <div class="grow">
                    <strong>{{ $partner->name }}</strong>
                    <div class="tiny muted">a préparé ça pour toi</div>
                </div>
                <span class="cel-cadeau-coeur">💝</span>
            </div>

            <div class="cel-message">{!! nl2br(e($celebration->message)) !!}</div>

            @if ($celebration->aUnSon())
                <div class="cel-bloc">
                    <div class="cel-bloc-t">🎧 Un son pour toi</div>
                    <audio class="cel-audio" controls preload="metadata" src="{{ $celebration->sonUrl() }}"></audio>
                </div>
            @endif

            @if ($celebration->aUneVideo())
                <div class="cel-bloc">
                    <div class="cel-bloc-t">🎬 Une vidéo pour toi</div>
                    <video class="cel-video" controls playsinline preload="metadata" src="{{ $celebration->videoUrl() }}"></video>
                </div>
            @endif

            @if (filled($celebration->activite))
                <div class="cel-bloc">
                    <div class="cel-bloc-t">🎁 Une activité</div>
                    <div>{{ $celebration->activite }}</div>
                </div>
            @endif

            @if (filled($celebration->promesse))
                <div class="cel-bloc">
                    <div class="cel-bloc-t">💍 Une promesse</div>
                    <div>{{ $celebration->promesse }}</div>
                </div>
            @endif
        </div>

        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-block mt16">← Retour</a>
    </div>
@endsection

@push('scripts')
    <script>
        // Confettis à l'ouverture du cadeau : deux UPA, sans bouton ni action.
        (function () {
            const zone = document.getElementById('confettis');
            if (!zone) return;

            const couleurs = ['#e63946', '#ff6b6b', '#ffd166', '#35d07f', '#4dabf7', '#b197fc', '#ffffff'];
            const formes = ['carre', 'rond', 'rect'];

            for (let i = 0; i < 90; i++) {
                const c = document.createElement('i');
                const forme = formes[i % formes.length];

                c.className = 'confetti confetti-' + forme;
                c.style.left = (Math.random() * 100) + '%';
                c.style.background = couleurs[i % couleurs.length];
                c.style.animationDelay = (Math.random() * 0.7).toFixed(2) + 's';
                c.style.animationDuration = (2.8 + Math.random() * 0.9).toFixed(2) + 's';
                c.style.setProperty('--rot', Math.floor(Math.random() * 720 - 360) + 'deg');
                c.style.setProperty('--derive', (Math.random() * 160 - 80).toFixed(0) + 'px');

                zone.appendChild(c);
            }

            setTimeout(function () { zone.remove(); }, 4500);
        })();
    </script>
@endpush
