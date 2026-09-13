@extends('layouts.app')

@section('title', 'Mission Secrète')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🕵️</div>
            <h1 class="title">Mission Secrète</h1>
            <p class="subtitle">Chaque jour à 00h, une mission arrive pour toi. L'autre ne saura jamais… sauf si la question du soir le/la trahit.</p>
        </div>

        {{-- Ma mission du jour --}}
        <section class="section-head"><h2>Ma mission du jour</h2></section>
        <div class="card mb16" style="padding:16px 18px">
            @if (! $maMission)
                <div class="center">
                    <div style="font-size:26px">🌙</div>
                    <strong class="block">Rien pour l'instant</strong>
                    <div class="tiny muted">Ta mission du jour est en préparation. Reviens quelques minutes après 00h !</div>
                </div>
            @elseif ($maMission->statut === 'en_attente')
                <div class="flex between items-center gap12">
                    <div>
                        <strong>🔒 Une mission secrète t'attend</strong>
                        <div class="tiny muted">Accepte-la pour la découvrir, ou refuse-la.</div>
                    </div>
                    <div class="flex gap8">
                        <button class="btn btn-sm btn-ghost" onclick="refuserMission({{ $maMission->id }})">Refuser</button>
                        <button class="btn btn-sm btn-primary" onclick="accepterMission({{ $maMission->id }})">Accepter</button>
                    </div>
                </div>
            @else
                <div style="font-size:14px">
                    @if (in_array($maMission->statut, ['en_cours', 'accomplie']))
                        <span class="badge {{ $maMission->statut === 'en_cours' ? 'succes' : 'neutre' }}">{{ $maMission->statut === 'en_cours' ? '🕐 En cours' : '🕵️ Accomplie' }}</span>
                        <span class="block mt8">{{ $maMission->texte }}</span>
                    @else
                        <span class="badge neutre">
                            @if ($maMission->statut === 'refusee')
                                🚫 Refusée
                            @elseif ($maMission->statut === 'demasquee')
                                😏 Démasquée
                            @else
                                💤 Échouée
                            @endif
                        </span>
                    @endif

                    <div class="tiny muted mt8" style="line-height:1.6">
                        @if ($maMission->statut === 'en_cours' && $maMission->date_fin)
                            <span style="color:var(--warning)">⏳ À accomplir avant 20h ({{ $maMission->date_fin->diffForHumans(['parts' => 1]) }}).</span>
                        @endif
                        @if ($maMission->statut === 'accomplie' && ! $maMission->devine)
                            <b>En attente de la question du soir de {{ $partner->name }}.</b>
                        @endif
                        @if ($maMission->statut === 'accomplie' && $maMission->devine === 'spontane')
                            <b>Ton/ta partenaire a répondu « Non » : mission réussie en secret, +25 pts ✅</b>
                        @endif
                        @if ($maMission->statut === 'demasquee')
                            <b>Ton/ta partenaire t'a démasqué·e : +10 pts chacun (−)</b>
                        @endif
                        @if ($maMission->statut === 'refusee')
                            <span>Tu as décliné la mission du jour. Aucun point, mais tu peux refaire une mission demain !</span>
                        @endif
                        @if ($maMission->statut === 'echouee')
                            <span>Mission non accomplie aujourd'hui. À demain pour une nouvelle occasion !</span>
                        @endif
                        @if ($maMission->vue_par_partenaire && in_array($maMission->statut, ['accomplie', 'demasquee']))
                            <div class="mt8"><span style="color:var(--success)">✓ {{ $partner->name }} a vu la question du soir.</span></div>
                        @endif
                    </div>
                </div>

                @if ($maMission->statut === 'en_cours')
                    <div class="flex gap8 mt16">
                        <button class="btn btn-sm btn-ghost" onclick="abandonnerMission({{ $maMission->id }})">💤 Abandonner</button>
                        <button class="btn btn-sm btn-primary" onclick="accomplirMission({{ $maMission->id }})">Accomplie ✅</button>
                    </div>
                @endif
            @endif
        </div>

        {{-- Question du soir --}}
        <section class="section-head"><h2>Question du soir</h2></section>
        <div class="card mb16" style="padding:16px 18px">
            @if (! $questionOuverte)
                <div class="flex between items-center">
                    <div>
                        <strong>🌙 À 20h, les masques tombent</strong>
                        <div class="tiny muted">La question « {{ $partner->name }} a-t-elle fait une mission secrète aujourd'hui ? » s'ouvre ce soir.</div>
                    </div>
                </div>
            @elseif (! $reponduAujourdhui)
                <div style="background:linear-gradient(135deg, rgba(245,158,11,.10), rgba(230,57,70,.05)), var(--card); border-color:rgba(245,158,11,.30)">
                    <strong>🌙 {{ $partner->name }} a-t-elle fait une mission secrète aujourd'hui ?</strong>
                    <p class="tiny muted mt8" style="line-height:1.5; margin-bottom:0">
                        Tu ne peux répondre qu'une seule fois par jour.
                        Peu importe ta réponse, tu ne sauras pas si une mission existait vraiment (sauf si tu démasques).
                    </p>
                    <div class="flex gap8 mt16">
                        <button class="btn btn-sm btn-primary" onclick="repondreQuestion('oui')">🎯 Oui, je le/la soupçonne</button>
                        <button class="btn btn-sm btn-ghost" onclick="repondreQuestion('non')">💗 Non, tout était spontané</button>
                    </div>
                </div>
            @else
                <strong class="block">🌙 Verdict du soir</strong>
                <div class="tiny mt8" style="line-height:1.6">
                    @if (str_starts_with($resultatDevin ?? '', 'demasquee'))
                        <b style="color:var(--success)">🎯 Oui ! Mission démasquée : +10 pts chacun.</b>
                    @elseif (($resultatDevin ?? '') === 'fausse')
                        <span class="muted">Fausse alerte : aucune mission n'était en jeu, aucun point.</span>
                    @elseif (str_starts_with($resultatDevin ?? '', 'ratee'))
                        <span class="muted">Raté : une mission était bien réelle, {{ $partner->name }} gagne +25 pts.</span>
                    @elseif (($resultatDevin ?? '') === 'rien')
                        <span class="muted">Rien à signaler : aucun point de part ni d'autre.</span>
                    @else
                        <span class="muted">Tu as répondu « {{ $derniereReponse === 'oui' ? 'Oui, je le/la soupçonne' : 'Non, tout était spontané' }} ». Résultat inconnu.</span>
                    @endif
                    <div class="muted mt8">Réponses du jour : 1/1.</div>
                </div>

                {{-- Révélation de la mission du/de la partenaire si elle est tranchée --}}
                @if ($saMission && $saMission->statut === 'demasquee')
                    <div class="block mt16" style="font-size:14px">
                        <span class="badge rouge">Démasquée</span>
                        Tu avais raison ! C'était la mission « {{ $saMission->texte }} » — +10 pts 🎯
                    </div>
                @elseif ($saMission && $saMission->statut === 'accomplie' && $saMission->devine === 'spontane')
                    <div class="block mt16" style="font-size:14px">
                        <span class="badge rouge">Raté</span>
                        C'était la mission « {{ $saMission->texte }} » — tu as répondu « Non ». {{ $partner->name }} gagne +25 pts 💨
                    </div>
                @endif
            @endif
        </div>

        {{-- La mission de mon/ma partenaire : rien tant que la question du soir n'a pas été répondue. --}}
        @if ($saMission && $reponduAujourdhui)
        <section class="section-head"><h2>La mission de {{ $partner->name }}</h2></section>
        <div class="card pad-sm">
            @if ($questionOuverte && in_array($saMission->statut, ['demasquee']) || ($questionOuverte && $saMission->statut === 'accomplie' && $saMission->devine === 'spontane'))
                <div class="row">
                    <div style="font-size:14px">
                        <span class="badge {{ $saMission->statut === 'demasquee' ? 'rouge' : '' }}">{{ $saMission->statut === 'demasquee' ? 'Démasquée' : 'Raté' }}</span>
                        <span class="block mt8">{{ $saMission->texte }}</span>
                    </div>
                </div>
            @else
                <div class="row">
                    <div>
                        <div class="hidden-card" style="max-width:220px">Ssshh… {{ $partner->name }} a peut-être une mission secrète en cours 🕵️</div>
                        <div class="tiny muted mt8">
                            @if ($saMission->statut === 'refusee' || $saMission->statut === 'echouee')
                                {{ $partner->name }} n'a pas de mission en cours aujourd'hui.
                            @elseif ($saMission->statut === 'accomplie' && $saMission->devine)
                                Mission déjà tranchée par la question du soir.
                            @else
                                Statut secret.
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @endif

        <div class="card mt16 center" style="background:rgba(14,116,144,.08); border-color:rgba(34,211,238,.25)">
            <strong>Comment ça marche ?</strong>
            <p class="tiny muted mt8" style="line-height:1.6; margin-bottom:0">
                ① Chaque jour à 00h, une mission arrive pour toi (accepte-la ou refuse-la) → ② réalise-la dans le vrai monde, en secret (personne n'est prévenu) → ③ à 20h, elle s'arrête et la question du soir s'ouvre.
                <b>+25 pts</b> si tu passes inaperçu·e (il/elle répond « Non »),
                <b>+10 pts chacun</b> s'il ou elle te démasque.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        async function accepterMission(id) {
            const res = await api('/jeux/mission-secrete/' + id + '/reveler', { method: 'POST' });
            if (res.ok) location.reload();
        }

        async function refuserMission(id) {
            if (!confirm('Refuser la mission du jour sans la découvrir ?')) return;
            const res = await api('/jeux/mission-secrete/' + id + '/refuser', { method: 'POST' });
            if (res.ok) location.reload();
        }

        async function accomplirMission(id) {
            if (!confirm('As-tu réellement accompli cette mission dans la vraie vie ?')) return;
            const res = await api('/jeux/mission-secrete/' + id + '/accomplir', { method: 'POST' });
            if (res.ok) {
                toast(res.data.message, 'success');
                setTimeout(() => location.reload(), 900);
            }
        }

        async function abandonnerMission(id) {
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