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

            @if ($celebration->aUnVocal())
                @php
                    $vocalBars = array_values(array_filter(
                        array_map('intval', explode(',', (string) $celebration->vocal_bars)),
                        fn ($v) => $v >= 0,
                    ));
                    if (count($vocalBars) < 2) {
                        $seed = (($celebration->id * 2654435761) % 100000);
                        $vocalBars = [];
                        for ($i = 0; $i < 36; $i++) {
                            $seed = ($seed * 9301 + 49297) % 233280;
                            $vocalBars[] = 25 + (int) round(($seed / 233280) * 55);
                        }
                    }
                    while (count($vocalBars) < 36) {
                        $vocalBars[] = end($vocalBars);
                    }
                    $vocalBars = array_slice($vocalBars, 0, 36);
                @endphp
                <div class="cel-bloc">
                    <div class="cel-bloc-t">🎙️ Un vocal pour toi</div>
                    <div class="disc-audio cel-vocal-cadeau" id="cel-vocal">
                        <div class="disc-vmsg-avatar">
                            <div class="disc-vmsg-avatar-img" @if ($partner->hasPhoto()) style="background-image:url('{{ $partner->photoUrl() }}')" @else style="background:{{ $partner->avatarColor() }}" @endif>@if (! $partner->hasPhoto()){{ mb_strtoupper(mb_substr($partner->name, 0, 1)) }}@endif</div>
                            <span class="disc-vmsg-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M18 11a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V21h2v-2.07A8 8 0 0 0 20 11h-2z"/></svg></span>
                        </div>
                        <button type="button" class="disc-audio-play" id="cel-vocal-play" aria-label="Écouter le vocal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </button>
                        <div class="disc-audio-body">
                            <div class="disc-vw">
                                <div class="disc-vw-layer">
                                    @foreach ($vocalBars as $hauteur)
                                        <span class="disc-vw-bar" style="height:{{ 4 + (int) round($hauteur / 100 * 28) }}px"></span>
                                    @endforeach
                                </div>
                                <div class="disc-vw-prog" id="cel-vocal-prog" style="width:0%">
                                    @foreach ($vocalBars as $hauteur)
                                        <span class="disc-vw-bar" style="height:{{ 4 + (int) round($hauteur / 100 * 28) }}px"></span>
                                    @endforeach
                                </div>
                            </div>
                            <span class="disc-audio-time" id="cel-vocal-time">0:00</span>
                        </div>
                    </div>
                    <audio id="cel-vocal-audio" src="{{ $celebration->vocalUrl() }}" preload="none" data-duration="{{ $celebration->vocal_duration }}"></audio>
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

@if ($celebration->aUnVocal())
    @push('scripts')
        <script>
            // Lecture du vocal (play/pause, progression sur la bande son, durée).
            (function () {
                const audio = document.getElementById('cel-vocal-audio');
                const playBtn = document.getElementById('cel-vocal-play');
                const prog = document.getElementById('cel-vocal-prog');
                const time = document.getElementById('cel-vocal-time');
                if (!audio || !playBtn || !prog || !time) return;

                const ICON_PLAY = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
                const ICON_PAUSE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';

                function fmt(sec) {
                    sec = Math.max(0, Math.round(Number(sec) || 0));
                    const m = Math.floor(sec / 60);
                    const s = sec % 60;
                    return m + ':' + (s < 10 ? '0' : '') + s;
                }

                const stockee = parseFloat(audio.dataset.duration || 0) || 0;
                const duree = () => (isFinite(audio.duration) && audio.duration > 0) ? audio.duration : stockee;

                audio.addEventListener('play', () => {
                    playBtn.innerHTML = ICON_PAUSE;
                    prog.style.width = '0%';
                });
                audio.addEventListener('timeupdate', () => {
                    const t = audio.currentTime;
                    const d = duree();
                    if (isFinite(t) && d > 0) {
                        prog.style.width = Math.min(100, (t / d) * 100) + '%';
                        if (!audio.paused) time.textContent = fmt(t);
                    }
                });
                audio.addEventListener('pause', () => {
                    playBtn.innerHTML = ICON_PLAY;
                    time.textContent = fmt(audio.currentTime);
                });
                audio.addEventListener('ended', () => {
                    playBtn.innerHTML = ICON_PLAY;
                    prog.style.width = '100%';
                    time.textContent = fmt(duree());
                });
                playBtn.addEventListener('click', () => {
                    if (audio.paused) {
                        audio.play().catch(() => {});
                    } else {
                        audio.pause();
                    }
                });
            })();
        </script>
    @endpush
@endif
