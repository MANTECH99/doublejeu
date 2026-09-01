@extends('layouts.app')

@section('title', 'Jeu des Enveloppes')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">💌</div>
            <h1 class="title">Le Jeu des Enveloppes</h1>
            <p class="subtitle">3 enveloppes rouges (osé), 3 bleues (tendre), 3 vertes (drôle). Choisis et assume.</p>
        </div>

        @if ($couple->enveloppes->isEmpty())
            <div class="card center mt16">
                <div style="font-size:38px; margin-bottom:8px">✉️</div>
                <h2 style="margin:0 0 8px">Nouvelle partie</h2>
                <p class="muted">18 enveloppes vont être tirées (3 de chaque couleur par joueur).</p>
                <form method="POST" action="{{ route('enveloppe.nouvelle') }}">
                    @csrf
                    <button class="btn btn-primary btn-block" type="submit">Tirer les enveloppes</button>
                </form>
            </div>

            <div class="card mt16 center">
                <strong>Comment ça marche ?</strong>
                <p class="tiny muted mt8">On alterne. Le gagnant final (le plus de défis réalisés) fait choisir au perdant une récompense. Défi réalisé = <b>+15 pts</b>, refusé = <b>−10 pts</b> et ton/ta partenaire gagne <b>+10</b>.</p>
            </div>
        @else
            {{-- Score --}}
            <div class="score-strip" id="score-strip">…</div>

            {{-- Zone d'état --}}
            <div class="card mt16 pad-sm" id="status-zone">
                <div class="flex between items-center">
                    <strong id="turn-label">Chargement…</strong>
                    <form method="POST" action="{{ route('enveloppe.nouvelle') }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost">🔄 Nouvelle partie</button>
                    </form>
                </div>
            </div>

            {{-- Récap récompense --}}
            <div class="card mt16 pad-sm" id="win-recap" style="display:none"></div>

            {{-- Mes enveloppes --}}
            <section class="section-head"><h2>Mes enveloppes</h2></section>
            <div id="mes-enveloppes"></div>

            {{-- Enveloppes du partenaire --}}
            <section class="section-head"><h2>Enveloppes de {{ $couple->partnerOf(auth()->user())->name }}</h2></section>
            <div id="ses-enveloppes"></div>

            {{-- Log --}}
            <section class="section-head"><h2>Actualités</h2></section>
            <div class="card pad-sm" id="log"></div>
        @endif
    </div>

    {{-- Modal défi --}}
    <div id="defi-modal" class="modal-ov" style="display:none" onclick="if(event.target===this) fermerModal()">
        <div class="modal center">
            <div id="defi-modal-emoji" style="font-size:44px">✉️</div>
            <span class="badge rouge" id="defi-modal-couleur">Enveloppe</span>
            <h3 style="margin-top:12px" id="defi-modal-texte"></h3>
            <div class="grid2 mt16">
                <button class="btn btn-danger" onclick="respondre(false)">🙅 Refuser</button>
                <button class="btn btn-primary" onclick="respondre(true)">🔥 J'ai réussi</button>
            </div>
            <button class="btn btn-ghost btn-sm btn-block mt8" onclick="fermerModal()">Plus tard</button>
        </div>
    </div>

    {{-- Modal victoire --}}
    <div id="win-modal" class="modal-ov" style="display:none" onclick="if(event.target===this) fermerWin()">
        <div class="modal center">
            <div style="font-size:48px; margin-bottom:6px">🏆</div>
            <h3>Partie terminée !</h3>
            <p class="muted" id="win-text"></p>
            <form onsubmit="event.preventDefault(); envoyerRecompense(this);" class="mt16">
                <label class="label" for="win-select">Choisis le/la perdant·e 🔻</label>
                <select class="select mb16" id="win-select">
                    @foreach ([$couple->user1, $couple->user2] as $j)
                        <option value="{{ $j->id }}" {{ $j->id === auth()->id() ? 'disabled' : 'selected' }}>{{ $j->name }}</option>
                    @endforeach
                </select>
                <label class="label">Récompense exigée</label>
                <input class="input mb16" name="texte" placeholder="Ex : un massage de 30 minutes" required>
                <button class="btn btn-primary btn-block">Exiger cette récompense ✨</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const coupleId = @json($couple->id);
        const meId = @json(auth()->id());
        const stateUrl = '/jeux/enveloppes/' + coupleId + '/etat';
        let currentEnv = null;
        let winNotified = false;
        let winModalPinned = false;

        const COLORS = { rouge: { label: 'Osé', ico: '🔥' }, bleue: { label: 'Tendre', ico: '💙' }, verte: { label: 'Drôle', ico: '💚' } };

        function renderEnvs(data) {
            const strip = document.getElementById('score-strip');
            const [p1, p2] = data.joueurs;
            strip.innerHTML = `
                <div class="score-player">
                    <div class="avatar sm" style="background:linear-gradient(135deg,#e63946,#ff6b6b)">${p1.name[0].toUpperCase()}</div>
                    <div><div class="who">${p1.name}</div><div class="pts">${p1.score}</div></div>
                </div>
                <div class="vs">VS</div>
                <div class="score-player" style="justify-content:flex-end">
                    <div style="text-align:right"><div class="who">${p2.name}</div><div class="pts">${p2.score}</div></div>
                    <div class="avatar sm" style="background:#fff">${p2.name[0].toUpperCase()}</div>
                </div>`;

            const turnEl = document.getElementById('turn-label');
            const actifName = data.joueurs.find(j => j.id === data.actifId)?.name || '';
            turnEl.innerHTML = data.terminee
                ? `<span class="badge succes">🏁 Partie terminée</span>`
                : `<span>🎯 À <b>${actifName}</b> de jouer</span>`;

            if (data.terminee) {
                const gagner = [...data.joueurs].sort((a, b) => b.score - a.score)[0];
                const perdant = [...data.joueurs].sort((a, b) => a.score - b.score)[0];
                document.getElementById('win-text').innerHTML = `<b>${gagner.name}</b> gagne !<br><span class="tiny muted">${perdant.name} doit offrir une récompense.</span>`;

                const recap = document.getElementById('win-recap');
                if (data.recompense) {
                    recap.style.display = 'block';
                    const extra = gagner.id === meId
                        ? `<button class="btn btn-sm btn-primary mt8" onclick="ouvrirRecompense()">Exiger une autre récompense ✨</button>`
                        : '';
                    recap.innerHTML = `
                        <div class="flex between items-center gap12">
                            <div style="font-size:13px">
                                <b>${data.recompense.gagnant}</b> exige : <span style="font-style:italic">« ${data.recompense.texte} »</span>
                                <div class="tiny muted">${data.recompense.perdant} doit encore l'offrir.</div>
                            </div>
                            ${extra}
                        </div>`;
                } else {
                    recap.style.display = 'none';
                }

                if (gagner.id === meId) {
                    if (!winModalPinned) {
                        document.getElementById('win-modal').style.display = data.recompenseEnvoyee ? 'none' : 'flex';
                    }
                } else {
                    document.getElementById('win-modal').style.display = 'none';
                    if (!winNotified) {
                        toast(`${gagner.name} remporte la partie !`, 'success');
                        winNotified = true;
                    }
                }
            }

            // Enveloppes par joueur
            const mineEnvs = data.enveloppes.filter(e => e.joueurId === meId);
            const theirsEnvs = data.enveloppes.filter(e => e.joueurId !== meId);

            const renderPlayer = (envs, mine) => {
                return ['rouge','bleue','verte'].map(couleur => {
                    const items = envs.filter(e => e.couleur === couleur);
                    const c = COLORS[couleur];
                    return `
                        <div style="flex:1">
                            <div class="tiny muted center mb8">${c.ico} ${c.label}</div>
                            <div style="display:grid; gap:8px">
                                ${items.map(e => `
                                    <button class="envelope ${couleur} ${e.statut !== 'disponible' ? 'done' : ''}"
                                            ${mine && e.statut === 'disponible' && !data.terminee ? '' : 'disabled'}
                                            onclick="ouvrir(${e.id})">
                                        <span class="e-ico">${e.statut === 'disponible' ? '✉️' : (e.statut === 'refusee' ? '💢' : '✓')}</span>
                                        <span class="e-label">${c.label}</span>
                                    </button>`).join('')}
                            </div>
                        </div>`;
                }).join('');
            };

            document.getElementById('mes-enveloppes').innerHTML = `<div class="flex gap12">${renderPlayer(mineEnvs, true)}</div>`;
            document.getElementById('ses-enveloppes').innerHTML = `<div class="flex gap12">${renderPlayer(theirsEnvs, false)}</div>`;

            // Log
            const log = document.getElementById('log');
            log.innerHTML = data.evenements.length
                ? data.evenements.map(e => `
                    <div class="row">
                        <span class="envelope ${e.couleur}" style="width:34px;height:44px;border-radius:8px;aspect-ratio:auto">
                            <span class="e-ico" style="font-size:14px">✉️</span>
                        </span>
                        <div class="grow" style="font-size:13px">
                            <b>${e.joueur}</b> : ${e.texte}
                            <small>${e.statut === 'refusee' ? 'refusé' : 'réalisé ✓'}</small>
                        </div>
                    </div>`).join('')
                : '<div class="tiny muted center" style="padding:8px">Aucun défi joué pour l\'instant.</div>';
        }

        async function ouvrir(id) {
            const res = await api(stateUrl.replace('/etat', '') + '/enveloppes/' + id + '/ouvrir', { method: 'POST' });
            if (res.ok) {
                currentEnv = id;
                const c = COLORS[res.data.couleur];
                document.getElementById('defi-modal-emoji').textContent = c.ico;
                document.getElementById('defi-modal-couleur').textContent = 'Enveloppe ' + c.label;
                document.getElementById('defi-modal-couleur').className = 'badge ' + res.data.couleur;
                document.getElementById('defi-modal-texte').textContent = res.data.defi;
                document.getElementById('defi-modal').style.display = 'flex';
                refreshEnvs();
            }
        }

        async function respondre(accepte) {
            if (!currentEnv) return;
            const res = await api(stateUrl.replace('/etat', '') + '/enveloppes/' + currentEnv + '/repondre', { method: 'POST', body: { accepte } });
            if (res.ok) {
                fermerModal();
                toast(res.data.message, accepte ? 'success' : 'info');
                refreshEnvs();
            }
        }

        async function envoyerRecompense(form) {
            const select = document.getElementById('win-select');
            const texte = form.querySelector('input[name="texte"]').value;
            const res = await api(stateUrl.replace('/etat', '') + '/recompense', { method: 'POST', body: { perdant_id: select.value, texte } });
            if (res.ok) {
                winModalPinned = false;
                fermerWin();
                toast(res.data.message, 'success');
            }
        }

        function fermerModal() {
            document.getElementById('defi-modal').style.display = 'none';
            currentEnv = null;
        }

        function ouvrirRecompense() {
            winModalPinned = true;
            document.getElementById('win-modal').style.display = 'flex';
        }

        function fermerWin() {
            winModalPinned = false;
            document.getElementById('win-modal').style.display = 'none';
        }

        async function refreshEnvs() {
            const res = await api(stateUrl, { json: false });
            if (res.ok) renderEnvs(res.data);
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(stateUrl, renderEnvs, { interval: 1600 });
        });
    </script>
@endpush