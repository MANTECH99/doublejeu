@extends('layouts.app')

@section('title', 'Mission Secrète')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🕵️</div>
            <h1 class="title">Mission Secrète</h1>
            <p class="subtitle">Une mission rien que pour toi. L'autre ne saura jamais… sauf si la question du soir le/la trahit.</p>
        </div>

        {{-- Question du soir : ne fuite rien (posée tous les jours, mission ou pas). 2 réponses max. --}}
        <section class="section-head"><h2>Question du soir</h2></section>
        @if ($nombreReponses < 5)
            <div class="card mb16" style="background:linear-gradient(135deg, rgba(245,158,11,.10), rgba(230,57,70,.05)), var(--card); border-color:rgba(245,158,11,.30)">
                <strong>🌙 Ton/ta partenaire a-t-il/elle fait une mission secrète aujourd'hui ?</strong>
                <p class="tiny muted mt8" style="line-height:1.5">
                    Tu peux répondre jusqu'à {{ 5 - $nombreReponses }} fois de plus aujourd'hui.
                    Peu importe ta réponse, tu ne sauras pas si une mission existait vraiment.
                </p>
                <div class="flex gap8 mt16">
                    <button class="btn btn-sm btn-primary" onclick="repondreQuestion('oui')">🎯 Oui, je le/la soupçonne</button>
                    <button class="btn btn-sm btn-ghost" onclick="repondreQuestion('non')">💗 Non, tout était spontané</button>
                </div>
            </div>
        @else
            <div class="card mb16" style="padding:12px 16px">
                <strong class="block">🌙 Verdict du soir</strong>
                <div class="tiny mt8" style="line-height:1.6">
                    @if (str_starts_with($resultatDevin ?? '', 'demasquee'))
                        <b style="color:var(--success)">🎯 Oui ! {{ (int) explode(':', $resultatDevin)[1] }} mission(s) démasquée(s) : +{{ (int) explode(':', $resultatDevin)[1] * 10 }} pts chacun.</b>
                    @elseif (($resultatDevin ?? '') === 'fausse')
                        <span class="muted">Fausse alerte : aucune mission n'était en jeu, aucun point.</span>
                    @elseif (str_starts_with($resultatDevin ?? '', 'ratee'))
                        <span class="muted">Raté : {{ (int) explode(':', $resultatDevin)[1] }} mission(s) bien réelle(s), ton/ta partenaire gagne +{{ (int) explode(':', $resultatDevin)[1] * 25 }} pts.</span>
                    @elseif (($resultatDevin ?? '') === 'rien')
                        <span class="muted">Rien à signaler : aucun point de part ni d'autre.</span>
                    @else
                        <span class="muted">Tu as répondu « {{ $derniereReponse === 'oui' ? 'Oui, je le/la soupçonne' : 'Non, tout était spontané' }} ». Résultat inconnu.</span>
                    @endif
                    <div class="muted mt8">
                        Réponses du jour : {{ $nombreReponses }}/5.
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb16">
            <div class="flex between items-center">
                <div>
                    <strong>Tirer une mission secrète</strong>
                    <div class="tiny muted">Une notification t'avertira. Le contenu reste caché jusqu'au clic.</div>
                </div>
            </div>
            <form id="form-mission" class="mt16" onsubmit="event.preventDefault(); nouvelleMission();">
                <label class="label">Fréquence des missions</label>
                <div class="grid2 mb16">
                    <select class="select" id="frequence">
                        <option value="24">⏰ 1 par jour</option>
                        <option value="48">💤 1 tous les 2 jours</option>
                        <option value="168">📅 1 par semaine</option>
                    </select>
                    <button class="btn btn-primary" type="submit">🎲 Tirer une mission</button>
                </div>
            </form>
        </div>

        {{-- Mes missions --}}
        <section class="section-head"><h2>Mes missions</h2></section>
        <div class="card pad-sm">
            @forelse ($mesMissions as $m)
                @include('jeux.mission._item', ['m' => $m, 'mine' => true])
            @empty
                <div class="tiny muted center" style="padding:10px">Pas encore de mission. Tire-en une !</div>
            @endforelse
        </div>

        {{-- Missions de mon/ma partenaire --}}
        <section class="section-head"><h2>Missions de {{ $partner->name }}</h2></section>
        <div class="card pad-sm">
            @forelse ($sesMissions as $m)
                @include('jeux.mission._item', ['m' => $m, 'mine' => false])
            @empty
                <div class="tiny muted center" style="padding:10px">{{ $partner->name }} n'a pas encore de mission secrète.</div>
            @endforelse
        </div>

        <div class="card mt16 center" style="background:rgba(14,116,144,.08); border-color:rgba(34,211,238,.25)">
            <strong>Comment ça marche ?</strong>
            <p class="tiny muted mt8" style="line-height:1.6; margin-bottom:0">
                ① Tire une mission cachée → ② réalise-la dans le vrai monde, en secret (personne n'est prévenu) → ③ coche « Accomplie ».
                Chaque soir, ton/ta partenaire répond à la question du soir.
                <b>+25 pts</b> si tu passes inaperçu·e (il/elle répond « Non »),
                <b>+10 pts chacun</b> s'il ou elle te démasque.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        async function nouvelleMission() {
            const freq = document.getElementById('frequence').value;
            const res = await api('/jeux/mission-secrete/nouvelle', { method: 'POST', body: { frequence: freq } });
            if (res.ok) {
                toast(res.data.message, 'success');
                setTimeout(() => location.reload(), 800);
            }
        }

        async function reveler(id) {
            const res = await api('/jeux/mission-secrete/' + id + '/reveler', { method: 'POST' });
            if (res.ok) location.reload();
        }

        async function accomplir(id) {
            if (!confirm('As-tu réellement accompli cette mission dans la vraie vie ?')) return;
            const res = await api('/jeux/mission-secrete/' + id + '/accomplir', { method: 'POST' });
            if (res.ok) {
                toast(res.data.message, 'success');
                setTimeout(() => location.reload(), 900);
            }
        }

        async function echouer(id) {
            if (!confirm('Abandonner cette mission ?')) return;
            const res = await api('/jeux/mission-secrete/' + id + '/echouer', { method: 'POST' });
            if (res.ok) location.reload();
        }

        async function repondreQuestion(val) {
            const res = await api('{{ route('mission.question') }}', { method: 'POST', body: { reponse: val } });
            if (res.ok) {
                toast(res.data.message, res.data.message.startsWith('Raté') || res.data.message.startsWith('Fausse') ? 'info' : 'success');
                setTimeout(() => location.reload(), 1100);
            }
        }
    </script>
@endpush