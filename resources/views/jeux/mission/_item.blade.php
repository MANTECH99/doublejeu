@php
    $statutLabels = [
        'en_attente' => '🔒 En attente',
        'en_cours' => '🕐 En cours',
        'accomplie' => '🕵️ Accomplie',
        'demasquee' => '😏 Démasquée',
        'echouee' => '💤 Échouée',
    ];
    $deadline = $m->date_fin ? $m->date_fin->diffForHumans(['parts' => 1]) : null;
@endphp

<div class="row">
    <div class="grow">
        {{-- Ma mission --}}
        @if ($mine)
            @if ($m->statut === 'en_attente')
                <div class="flex between items-center gap12">
                    <div>
                        <strong>🔒 Mission secrète en attente</strong>
                        <div class="tiny muted">Clique pour découvrir ce que tu dois faire.</div>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="reveler({{ $m->id }})">Découvrir</button>
                </div>
            @else
                <div style="font-size:14px">{{ $m->texte }}</div>
                <div class="tiny muted mt8">
                    <span class="badge {{ $m->statut === 'en_cours' ? 'succes' : 'neutre' }}">{{ $statutLabels[$m->statut] }}</span>
                    @if ($m->statut === 'en_cours' && $deadline)
                        · <span style="color:var(--warning)">⏳ délai : {{ $deadline }}</span>
                    @endif
                    @if ($m->statut === 'accomplie' && ! $m->devine)
                        · <b>en attente de la question du soir de {{ $partner->name }}</b>
                    @endif
                    @if ($m->statut === 'accomplie' && $m->devine === 'spontane')
                        · <b>Ton/ta partenaire a répondu « Non » : mission réussie en secret, +25 pts ✅</b>
                    @endif
                    @if ($m->statut === 'demasquee')
                        · <b>Ton/ta partenaire t'a démasqué·e : +10 pts chacun (−)</b>
                    @endif
                </div>
            @endif
        @else
            {{-- Mission du/de la partenaire : opaque tant que le soir n'a pas tranché --}}
            @if (in_array($m->statut, ['en_attente', 'en_cours']) || ($m->statut === 'accomplie' && ! $m->devine))
                <div class="hidden-card" style="max-width:220px">Ssshh… mission secrète en cours 🕵️</div>
                <div class="tiny muted mt8">{{ $statutLabels[$m->statut] }}</div>
            @elseif ($m->statut === 'demasquee')
                <div style="font-size:14px"><span class="badge rouge">Démasquée</span> Tu avais raison ! C'était la mission « {{ $m->texte }} » — +10 pts 🎯</div>
            @elseif ($m->statut === 'accomplie' && $m->devine === 'spontane')
                <div style="font-size:14px"><span class="badge rouge">Raté</span> C'était la mission « {{ $m->texte }} » — tu as répondu « Non ». {{ $partner->name }} gagne +25 pts 💨</div>
            @elseif ($m->statut === 'echouee')
                <div class="tiny muted">Mission abandonnée par {{ $partner->name }}. 🍃</div>
            @else
                <strong>Mission accomplie 🎉</strong>
            @endif
        @endif
    </div>

    @if ($mine && in_array($m->statut, ['en_cours'], true))
        <div class="flex gap8">
            <button class="btn btn-sm btn-ghost" onclick="echouer({{ $m->id }})" title="Abandonner">💤</button>
            <button class="btn btn-sm btn-primary" onclick="accomplir({{ $m->id }})">Accomplie ✅</button>
        </div>
    @endif
</div>