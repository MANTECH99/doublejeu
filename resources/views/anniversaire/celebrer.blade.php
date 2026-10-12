@extends('layouts.app')

@section('title', 'Anniversaire de '.$partner->name)

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🎂</div>
            <h1 class="title">L'anniversaire de {{ $partner->name }}</h1>
            <p class="subtitle">
                @if ($jours === 0)
                    C'est aujourd'hui ! 🎉
                @else
                    Dans {{ $jours }} jours
                @endif
                <span class="muted">· {{ $dateCelebration->translatedFormat('l j F Y') }}</span>
            </p>
        </div>

        <div class="card cel-secret">
            <div class="cel-secret-ico">🔒</div>
            <div class="grow">
                <strong>Tout ce que tu écris ici reste secret</strong>
                <div class="tiny muted">
                    Tu as jusqu'au {{ $dateCelebration->translatedFormat('j F Y') }} pour préparer
                    son cadeau. {{ $partner->name }} ne pourra le découvrir
                    <strong>que le jour de son anniversaire</strong> — et ne pourra rien y modifier.
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('anniversaire.enregistrer') }}" enctype="multipart/form-data" class="card"
            data-busy data-busy-label="Envoi en cours…" id="celebrer-form">
            @csrf

            <label class="label" for="message">Ce que tu veux lui dire 💌</label>
            <textarea class="textarea" id="message" name="message" rows="6" maxlength="2000" required
                placeholder="Joyeux anniversaire mon amour…">{{ old('message', $celebration?->message) }}</textarea>

            <label class="label mt16" for="son">Un son pour elle/lui 🎧 <span class="muted">(facultatif)</span></label>
            <input class="input" type="file" id="son" name="son" accept="audio/*,video/mp4">
            @if ($celebration?->aUnSon())
                <audio class="cel-audio" controls preload="none" src="{{ $celebration->sonUrl() }}"></audio>
                <label class="cel-check tiny">
                    <input type="checkbox" name="supprimer_son" value="1"> Supprimer ce son
                </label>
            @endif

            <label class="label mt16" for="vocal-mic-btn">Un vocal pour elle/lui 🎙️ <span class="muted">(facultatif)</span></label>
            <div class="cel-vocal" id="vocal-zone">
                <div class="cel-vocal-idle">
                    <button type="button" class="cel-vocal-mic" id="vocal-mic-btn" aria-label="Enregistrer un vocal">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="2" width="6" height="12" rx="3"></rect>
                            <path d="M5 10a7 7 0 0 0 14 0"></path>
                            <line x1="12" y1="19" x2="12" y2="22"></line>
                        </svg>
                    </button>
                    <span class="cel-vocal-hint" id="vocal-hint">Envoie un Vocal et dis ce que tu veux</span>
                </div>

                <div class="cel-vocal-bar" id="vocal-bar" style="display:none">
                    <button type="button" class="disc-rec-cancel" id="vocal-cancel" aria-label="Annuler l'enregistrement">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <div class="disc-rec-center">
                        <span class="disc-rec-time" id="vocal-time">0:00</span>
                        <div class="disc-rec-waves" id="vocal-waves"></div>
                    </div>
                    <button type="button" class="disc-rec-send" id="vocal-send" aria-label="Stopper et garder le vocal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                        </svg>
                    </button>
                </div>

                <div class="cel-vocal-preview" id="vocal-preview"></div>

                <label class="cel-check tiny" id="vocal-suppr-wrap" style="display:none">
                    <input type="checkbox" name="supprimer_vocal" value="1" id="vocal-suppr"> Supprimer ce vocal
                </label>
            </div>

            <input type="hidden" name="vocal_path" id="vocal-path-input" value="{{ $celebration?->vocal_path }}">
            <input type="hidden" name="vocal_duration" id="vocal-duration-input" value="{{ $celebration?->vocal_duration ?? '' }}">
            <input type="hidden" name="vocal_bars" id="vocal-bars-input" value="{{ $celebration?->vocal_bars ?? '' }}">

            <label class="label mt16" for="activite">Une activité à lui offrir 🎁 <span class="muted">(facultatif)</span></label>
            <input class="input" id="activite" name="activite" maxlength="255" value="{{ old('activite', $celebration?->activite) }}"
                placeholder="Ex : une soirée resto + un bouquet">

            <label class="label mt16" for="promesse">Une promesse à lui faire 💍 <span class="muted">(facultatif)</span></label>
            <input class="input" id="promesse" name="promesse" maxlength="255" value="{{ old('promesse', $celebration?->promesse) }}"
                placeholder="Ex : je viens diner avec toi samedi">

            <label class="label mt16" for="video">Une vidéo pour lui <span class="muted">(facultatif)</span></label>
            <input class="input" type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg,video/quicktime">
            @if ($celebration?->video_path)
                <video class="cel-audio" controls preload="none" src="{{ $celebration->videoUrl() }}"></video>
                <label class="cel-check tiny">
                    <input type="checkbox" name="supprimer_video" value="1"> Supprimer cette vidéo
                </label>
            @endif

            <button type="submit" class="btn btn-primary btn-block mt16">
                <span>{{ $celebration ? 'Mettre à jour mon cadeau' : 'Offrir ce cadeau' }}</span>
            </button>
        </form>

        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-block mt16">← Retour</a>
    </div>
@endsection

@php
    $vocalInit = [
        'photo' => $partner->hasPhoto() ? $partner->photoUrl() : null,
        'color' => $partner->avatarColor(),
        'initial' => mb_strtoupper(mb_substr($partner->name, 0, 1)),
        'vocal' => $celebration?->aUnVocal() ? [
            'url' => $celebration->vocalUrl(),
            'duration' => $celebration->vocal_duration,
            'bars' => $celebration->vocal_bars,
        ] : null,
    ];
@endphp

@push('scripts')
    <script type="application/json" id="vocal-init">@json($vocalInit)</script>
    <script>
        (function () {
            const ZONE = document.getElementById('vocal-zone');
            if (!ZONE) return;

            const FORM = document.getElementById('celebrer-form');
            const SUBMIT_BTN = FORM ? FORM.querySelector('[type="submit"]') : null;
            const MIC_BTN = document.getElementById('vocal-mic-btn');
            const HINT = document.getElementById('vocal-hint');
            const BAR = document.getElementById('vocal-bar');
            const TIME = document.getElementById('vocal-time');
            const WAVES = document.getElementById('vocal-waves');
            const CANCEL = document.getElementById('vocal-cancel');
            const SEND = document.getElementById('vocal-send');
            const PREVIEW = document.getElementById('vocal-preview');
            const SUPPR_WRAP = document.getElementById('vocal-suppr-wrap');
            const SUPPR = document.getElementById('vocal-suppr');
            const PATH_IN = document.getElementById('vocal-path-input');
            const DUR_IN = document.getElementById('vocal-duration-input');
            const BARS_IN = document.getElementById('vocal-bars-input');

            const UPLOAD_URL = '{{ route('anniversaire.vocal') }}';

            const ICON_PLAY = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
            const ICON_PAUSE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';
            const ICON_MIC = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M18 11a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V21h2v-2.07A8 8 0 0 0 20 11h-2z"/></svg>';

            let micRecorder = null;
            let micStream = null;
            let micCtx = null;
            let micAnalyser = null;
            let micFreq = null;
            let micStartedAt = 0;
            let micTimer = null;
            let micChunks = [];
            let waveBars = [];
            let waveAnim = null;

            function formatAudioTime(sec) {
                sec = Number(sec);
                if (!isFinite(sec) || sec < 0) return '0:00';
                sec = Math.round(sec);
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }

            function parseBars(str, n) {
                if (!str) return null;
                const arr = String(str).split(',').map((v) => parseInt(v, 10)).filter((v) => isFinite(v));
                if (arr.length < 2) return null;
                while (arr.length < n) arr.push(arr[arr.length - 1]);
                return arr.slice(0, n);
            }

            // Repli si le vocal est daté (sans bande son) : motif déterministe.
            function barsFromSeed(seed, n) {
                seed = ((Number(seed) || 1) * 2654435761) % 100000;
                const arr = [];
                for (let i = 0; i < n; i++) {
                    seed = (seed * 9301 + 49297) % 233280;
                    arr.push(25 + Math.round((seed / 233280) * 55));
                }
                return arr;
            }

            // Durée réelle (secondes) d'un vocal : les blobs MediaRecorder n'ont
            // pas de durée dans l'en-tête, le décodage donne la vraie valeur.
            function realAudioDuration(url) {
                const Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
                if (!Ctx) return Promise.resolve(0);
                return fetch(url, { credentials: 'same-origin' })
                    .then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error('http'))))
                    .then((buf) => new Promise((resolve) => {
                        const ctx = new Ctx(1, 1, 44100);
                        const finish = (b) => resolve(b.duration || 0);
                        try {
                            const p = ctx.decodeAudioData(buf);
                            if (p && typeof p.then === 'function') p.then(finish).catch(() => resolve(0));
                            else ctx.decodeAudioData(buf.slice(0), finish, () => resolve(0));
                        } catch (e) {
                            resolve(0);
                        }
                    }));
            }

            // Hauteurs (0-100) de la bande son réelle du vocal enregistré.
            function computeAudioBars(blob) {
                const Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
                if (!Ctx) return Promise.resolve('');
                return new Promise((resolve) => {
                    const ctx = new Ctx(1, 1, 44100);
                    const reader = new FileReader();
                    reader.onload = () => {
                        const finish = (buffer) => {
                            try {
                                const n = 36;
                                const channel = buffer.getChannelData(0);
                                const block = Math.floor(channel.length / n) || 1;
                                const bars = [];
                                for (let i = 0; i < n; i++) {
                                    let sum = 0;
                                    const start = i * block;
                                    const end = Math.min(channel.length, start + block);
                                    for (let j = start; j < end; j++) sum += Math.abs(channel[j]);
                                    const avg = sum / Math.max(1, end - start);
                                    bars.push(Math.max(10, Math.min(100, Math.round(avg * 220))));
                                }
                                resolve(bars.join(','));
                            } catch (e) {
                                resolve('');
                            }
                        };
                        try {
                            const p = ctx.decodeAudioData(reader.result);
                            if (p && typeof p.then === 'function') p.then(finish).catch(() => resolve(''));
                            else ctx.decodeAudioData(reader.result.slice(0), finish, () => resolve(''));
                        } catch (e) {
                            resolve('');
                        }
                    };
                    reader.onerror = () => resolve('');
                    reader.readAsArrayBuffer(blob);
                });
            }

            async function uploadAudioBlob(blob) {
                const fd = new FormData();
                const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
                fd.append('audio', blob, 'vocal.' + ext);
                try {
                    const res = await fetch(UPLOAD_URL, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                    if (res.ok) return await res.json();
                    const err = await res.json().catch(() => ({}));
                    toast(err.message || 'Échec de l\'enregistrement vocal.', 'error');
                } catch (e) {
                    toast('Connexion perdue.', 'error');
                }
                return null;
            }

            let INIT = null;

            function avatarEl() {
                const wrap = document.createElement('div');
                wrap.className = 'disc-vmsg-avatar';
                const img = document.createElement('div');
                img.className = 'disc-vmsg-avatar-img';
                if (INIT && INIT.photo) {
                    img.style.backgroundImage = "url('" + INIT.photo + "')";
                } else {
                    img.style.background = INIT ? INIT.color : '#E63946';
                    img.textContent = INIT ? (INIT.initial || '?') : '?';
                }
                wrap.appendChild(img);
                const badge = document.createElement('span');
                badge.className = 'disc-vmsg-badge';
                badge.setAttribute('aria-hidden', 'true');
                badge.innerHTML = ICON_MIC;
                wrap.appendChild(badge);
                return wrap;
            }

            function playIconBtn() {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'disc-audio-play';
                btn.setAttribute('aria-label', 'Écouter le vocal');
                btn.innerHTML = ICON_PLAY;
                return btn;
            }

            function buildBars(bars, n) {
                const frag = document.createDocumentFragment();
                bars.forEach((h) => {
                    const bar = document.createElement('span');
                    bar.className = 'disc-vw-bar';
                    bar.style.height = (4 + Math.round(h / 100 * 28)) + 'px';
                    frag.appendChild(bar);
                });
                return frag;
            }

            function buildPreview(url, duration, bars) {
                PREVIEW.innerHTML = '';
                const wrap = document.createElement('div');
                wrap.className = 'disc-audio cel-vocal-cadeau';
                wrap.appendChild(avatarEl());
                wrap.appendChild(playIconBtn());

                const body = document.createElement('div');
                body.className = 'disc-audio-body';

                const vw = document.createElement('div');
                vw.className = 'disc-vw';
                const base = document.createElement('div');
                base.className = 'disc-vw-layer';
                const prog = document.createElement('div');
                prog.className = 'disc-vw-prog';
                prog.style.width = '0%';
                const barItems = buildBars(bars, 36);
                base.appendChild(barItems);
                prog.appendChild(barItems.cloneNode(true));
                vw.appendChild(base);
                vw.appendChild(prog);

                const time = document.createElement('span');
                time.className = 'disc-audio-time';
                time.textContent = formatAudioTime(0);

                const audio = document.createElement('audio');
                audio.src = url;
                audio.preload = 'none';
                audio.dataset.duration = String(duration || 0);

                body.appendChild(vw);
                body.appendChild(time);
                wrap.appendChild(body);
                wrap.appendChild(audio);
                PREVIEW.appendChild(wrap);
                wireVocal(wrap);
            }

            function wireVocal(wrap) {
                const playBtn = wrap.querySelector('.disc-audio-play');
                const prog = wrap.querySelector('.disc-vw-prog');
                const time = wrap.querySelector('.disc-audio-time');
                const audio = wrap.querySelector('audio');
                if (!playBtn || !prog || !time || !audio) return;

                const totalMs = parseFloat(audio.dataset.duration || 0) || 0;
                let realDur = 0;
                realAudioDuration(audio.src).then((d) => {
                    if (d > 0) {
                        realDur = d;
                        time.textContent = formatAudioTime(d);
                    }
                });
                const effDuration = () => realDur > 0 ? realDur
                    : ((isFinite(audio.duration) && audio.duration > 0) ? audio.duration : totalMs);

                const setIcon = (playing) => { playBtn.innerHTML = playing ? ICON_PAUSE : ICON_PLAY; };
                const setProgress = (pct) => { prog.style.width = Math.min(100, Math.max(0, pct)) + '%'; };
                const showElapsed = () => {
                    const t = audio.currentTime;
                    time.textContent = formatAudioTime((isFinite(t) && t >= 0) ? t : (realDur || totalMs));
                };

                audio.addEventListener('play', () => { setIcon(true); setProgress(0); });
                audio.addEventListener('timeupdate', () => {
                    const t = audio.currentTime;
                    if (isFinite(t) && effDuration() > 0) {
                        setProgress((t / effDuration()) * 100);
                        if (!audio.paused) showElapsed();
                    }
                });
                audio.addEventListener('pause', () => { setIcon(false); showElapsed(); });
                audio.addEventListener('ended', () => {
                    setIcon(false);
                    setProgress(100);
                    time.textContent = formatAudioTime(realDur || totalMs);
                });
                playBtn.addEventListener('click', () => {
                    if (audio.paused) {
                        audio.play().catch(() => toast('Lecture impossible.', 'error'));
                    } else {
                        audio.pause();
                    }
                });
            }

            function isRecording() {
                return micRecorder !== null;
            }

            function setBodyRecording(on) {
                MIC_BTN.classList.toggle('recording', on);
                BAR.style.display = on ? 'flex' : 'none';
                if (SUBMIT_BTN) SUBMIT_BTN.disabled = on;
            }

            function showRecorder() {
                MIC_BTN.style.display = 'grid';
                HINT.textContent = 'Appuie sur le micro pour enregistrer à nouveau ta voix';
                setBodyRecording(false);
            }

            function buildLiveWaves() {
                WAVES.innerHTML = '';
                waveBars = [];
                for (let i = 0; i < 30; i++) {
                    const b = document.createElement('span');
                    b.className = 'disc-rec-wave';
                    WAVES.appendChild(b);
                    waveBars.push(b);
                }
            }

            function startWaveAnim() {
                if (!micAnalyser) return;
                micFreq = micFreq || new Uint8Array(micAnalyser.frequencyBinCount);
                const tick = () => {
                    micAnalyser.getByteFrequencyData(micFreq);
                    const step = Math.max(1, Math.floor(micFreq.length / waveBars.length));
                    for (let i = 0; i < waveBars.length; i++) {
                        const v = micFreq[i * step] / 255;
                        waveBars[i].style.height = (5 + Math.round(v * 28)) + 'px';
                    }
                    waveAnim = requestAnimationFrame(tick);
                };
                waveAnim = requestAnimationFrame(tick);
            }

            function stopWaveAnim() {
                if (waveAnim) cancelAnimationFrame(waveAnim);
                waveAnim = null;
            }

            function stopMicStream() {
                if (micStream) {
                    micStream.getTracks().forEach((t) => t.stop());
                    micStream = null;
                }
                if (micCtx && micCtx.state !== 'closed') micCtx.close().catch(() => {});
                micCtx = null;
                micAnalyser = null;
                micFreq = null;
            }

            function startRecording() {
                if (isRecording()) return;
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    toast('Enregistrement non supporté sur cet appareil.', 'error');
                    return;
                }
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then((stream) => {
                        micStream = stream;
                        micCtx = new (window.AudioContext || window.webkitAudioContext)();
                        if (micCtx.state === 'suspended') micCtx.resume().catch(() => {});
                        micAnalyser = micCtx.createAnalyser();
                        micAnalyser.fftSize = 256;
                        micAnalyser.smoothingTimeConstant = 0.65;
                        micCtx.createMediaStreamSource(stream).connect(micAnalyser);

                        const mime = MediaRecorder.isTypeSupported('audio/webm')
                            ? 'audio/webm'
                            : (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '');
                        let rec;
                        try {
                            rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                        } catch (e) {
                            rec = new MediaRecorder(stream);
                        }
                        micRecorder = rec;
                        micChunks = [];
                        rec.ondataavailable = (e) => { if (e.data && e.data.size > 0) micChunks.push(e.data); };
                        rec.start(250);
                        micStartedAt = Date.now();

                        buildLiveWaves();
                        startWaveAnim();
                        MIC_BTN.style.display = 'none';
                        TIME.textContent = '0:00';
                        setBodyRecording(true);
                        micTimer = setInterval(() => {
                            const secs = Math.max(0, Math.round((Date.now() - micStartedAt) / 1000));
                            TIME.textContent = formatAudioTime(secs);
                        }, 250);
                    })
                    .catch(() => toast('Micro inaccessible : vérifie le navigateur.', 'error'));
            }

            function cancelRecording() {
                if (!isRecording()) return;
                const rec = micRecorder;
                micRecorder = null;
                micChunks = [];
                if (rec && rec.state !== 'inactive') {
                    rec.onstop = null;
                    rec.stop();
                }
                clearInterval(micTimer);
                micTimer = null;
                stopMicStream();
                stopWaveAnim();
                showRecorder();
            }

            async function finishRecording() {
                if (!isRecording()) return;
                SEND.disabled = true;
                const rec = micRecorder;
                micRecorder = null;
                // On attend la fin réelle du recorder AVANT de vider micChunks :
                // le dernier morceau (et l'en-tête contenu dans les premiers)
                // doit être inclus, sinon le blob est illisible.
                const blob = await new Promise((resolve) => {
                    rec.onstop = () => resolve(new Blob(micChunks, { type: rec.mimeType || 'audio/webm' }));
                    if (rec.state !== 'inactive') rec.stop();
                });
                micChunks = [];
                const duration = Math.max(1, Math.round((Date.now() - micStartedAt) / 1000));
                clearInterval(micTimer);
                micTimer = null;
                stopMicStream();
                stopWaveAnim();
                showRecorder();

                if (blob.size === 0) {
                    SEND.disabled = false;
                    return;
                }
                const bars = await computeAudioBars(blob);
                const data = await uploadAudioBlob(blob);
                SEND.disabled = false;
                if (!data) return;

                PATH_IN.value = data.path;
                DUR_IN.value = String(duration);
                BARS_IN.value = bars;
                if (SUPPR) SUPPR.checked = false;
                buildPreview(data.url, duration, parseBars(bars, 36) || barsFromSeed(duration, 36));
                SUPPR_WRAP.style.display = 'block';
                HINT.textContent = 'Appuie encore pour refaire ton vocal';
            }

            MIC_BTN.addEventListener('click', startRecording);
            CANCEL.addEventListener('click', cancelRecording);
            SEND.addEventListener('click', finishRecording);

            const initRaw = document.getElementById('vocal-init');
            if (initRaw) {
                try { INIT = JSON.parse(initRaw.textContent); } catch (e) { INIT = null; }
                if (INIT && INIT.vocal) {
                    buildPreview(INIT.vocal.url, INIT.vocal.duration || 0, parseBars(INIT.vocal.bars, 36) || barsFromSeed(INIT.vocal.duration || 0, 36));
                    SUPPR_WRAP.style.display = 'block';
                    HINT.textContent = 'Appuie encore pour refaire ton vocal';
                }
            }
        })();
    </script>
@endpush