@extends('layouts.app')

@section('title', 'Récompenses')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🏆</div>
            <h1 class="title">Récompenses</h1>
            <p class="subtitle">Plus vous jouez ensemble, plus les récompenses se débloquent.</p>
        </div>

        <div class="card">
            <div class="flex between items-center">
                <div class="grid2" style="width:100%">
                    @foreach ([$couple->user1, $couple->user2] as $j)
                        <div class="center">
                            <x-avatar :user="$j" class="sm" style="; display:inline-grid" />
                            <div class="mt8 tiny muted">{{ $j->name }}</div>
                            <div style="font-size:20px; font-weight:800" class="gold">{{ $scores[$j->id] }}</div>
                            <div class="tiny muted">points</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <section class="section-head"><h2>Seuils débloqués par le couple</h2></section>
        <div class="threshold-list">
            @foreach ([100 => 'Massage', 250 => 'Dîner surprise', 500 => 'Exaucer un souhait', 1000 => 'Récompense personnalisée'] as $seuil => $lib)
                <div class="threshold {{ $couple->score_total >= $seuil ? 'unlocked' : '' }}">
                    <span>{{ $lib }}</span>
                    <span class="t-pts">{{ $seuil }} pts</span>
                </div>
            @endforeach
        </div>

        <section class="section-head">
            <h2>Récompenses due</h2>
            <button class="btn btn-sm btn-soft" onclick="ajouterRecompense()">+ Nouvelle</button>
        </section>

        <div class="card pad-sm">
            @forelse ($recompenses as $r)
                <div class="row">
                    <span style="font-size:24px">{{ $r->statut === 'offerte' ? '✅' : '🎁' }}</span>
                    <div class="grow">
                        <div style="font-size:14px"><b>{{ $r->gagnant->name }}</b> exige : {{ $r->texte }}</div>
                        <div class="tiny muted">{{ $r->perdant->name }} {!! $r->statut === 'offerte' ? '<span style="color:var(--success)">a offert cette récompense</span>' : 'doit encore l\'offrir' !!}</div>
                    </div>
                    @if ($r->statut === 'due')
                        <button class="btn btn-sm btn-ghost" onclick="marquer({{ $r->id }})">Offerte ✓</button>
                    @endif
                </div>
            @empty
                <div class="tiny muted center" style="padding:10px">
                    Aucune récompense pour l'instant. Accumulez 100 points pour débloquer le premier palier !
                </div>
            @endforelse
        </div>
    </div>

    <div id="rew-modal" class="modal-ov" style="display:none" onclick="if(event.target===this) this.style.display='none'">
        <div class="modal">
            <h3>Ajouter une récompense 🎁</h3>
            <form onsubmit="event.preventDefault(); creerRecompense(this);">
                <label class="label" for="rew-text">Que doit faire ton/ta partenaire ?</label>
                <input class="input" id="rew-text" name="texte" placeholder="Ex : cuisiner mon plat préféré" required>
                <button class="btn btn-primary btn-block mt16">Exiger cette récompense</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        async function marquer(id) {
            const res = await api('/recompenses/' + id + '/marquer', { method: 'POST' });
            if (res.ok) {
                toast(res.data.message, 'success');
                setTimeout(() => location.reload(), 700);
            }
        }
        function ajouterRecompense() { document.getElementById('rew-modal').style.display = 'flex'; }
        async function creerRecompense(form) {
            const texte = form.querySelector('input').value;
            const res = await api('/recompenses', { method: 'POST', body: { texte } });
            if (res.ok) {
                document.getElementById('rew-modal').style.display = 'none';
                setTimeout(() => location.reload(), 500);
            }
        }
    </script>
@endpush