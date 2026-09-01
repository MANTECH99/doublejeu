@extends('layouts.app')

@section('title', 'Vérité ou Action')

@section('content')
    <div class="fadeIn">
        <div class="center mb16">
            <span class="badge {{ $partie->niveau }}" style="font-size:15px; padding:8px 16px">
                {{ $partie->niveau === 'doux' ? '🍑 Doux' : ($partie->niveau === 'chaud' ? '🔥 Chaud' : '🌶️ Brûlant') }}
            </span>
        </div>

        {{-- Score --}}
        <div class="score-strip" id="score-strip">
            <div class="score-player" data-player="1">
                <x-avatar :user="$couple->user1" class="sm" style="; background:linear-gradient(135deg,#e63946,#ff6b6b)" />
                <div>
                    <div class="who">{{ $couple->user1->name }}</div>
                    <div class="pts">0</div>
                </div>
            </div>
            <div class="vs">VS</div>
            <div class="score-player" style="justify-content:flex-end" data-player="2">
                <div style="text-align:right">
                    <div class="who">{{ $couple->user2->name }}</div>
                    <div class="pts">0</div>
                </div>
                <x-avatar :user="$couple->user2" class="sm" />
            </div>
        </div>

        {{-- Zone de jeu --}}
        <div class="card mt16" id="game-zone" style="min-height:280px; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center">
            <div class="spinner" id="loading"></div>
            <div class="muted mt8" id="waiting-hint" style="display:none">En attente de l'autre joueur…</div>

            <div id="stage-choix" style="display:none; width:100%">
                <div style="font-size:42px; margin-bottom:8px">🎯</div>
                <h2 id="stage-title-choix" style="margin:0 0 4px">À toi de jouer !</h2>
                <p class="muted" style="margin:0 0 18px">Choisis Vérité ou Action.</p>
                <div class="grid2">
                    <button class="btn btn-soft btn-lg" onclick="choisir('verite')">💬 Vérité</button>
                    <button class="btn btn-primary btn-lg" onclick="choisir('action')">🎬 Action</button>
                </div>
            </div>

            <div id="stage-carte" style="display:none; width:100%">
                <div style="font-size:40px; margin-bottom:8px" id="carte-ico">💬</div>
                <span class="badge neutre" id="carte-type">Vérité</span>
                <h3 id="carte-texte" style="margin:16px 0; font-size:20px; line-height:1.4; min-height:80px"></h3>
                <div class="grid2 mt8" id="carte-actions">
                    <button class="btn btn-danger" onclick="repondre(false)">🙅 Refuser</button>
                    <button class="btn btn-primary" onclick="repondre(true)">✅ Accepter</button>
                </div>
                <div id="verite-answer" style="display:none; width:100%">
                    <label class="label" for="verite-reponse">Ta vérité par rapport à cette question…</label>
                    <textarea class="input" id="verite-reponse" rows="3" maxlength="2000" placeholder="Écris ici ta réponse…"></textarea>
                    <div class="grid2 mt8">
                        <button class="btn btn-danger" onclick="repondre(false)">🙅 Refuser</button>
                        <button class="btn btn-primary" onclick="envoyerVerite()">💌 Envoyer ma vérité</button>
                    </div>
                </div>
            </div>

            <div id="stage-gage" style="display:none; width:100%">
                <div style="font-size:40px; margin-bottom:8px">😅</div>
                <span class="badge danger">Gage</span>
                <h3 id="gage-texte" style="margin:16px 0; font-size:19px; line-height:1.4"></h3>
                <p class="tiny muted">Réalise ce gage en privé. Ton/ta partenaire ne saura pas que… si.</p>
            </div>

            <div id="stage-validation" style="display:none; width:100%">
                <div style="font-size:40px; margin-bottom:8px">🕵️</div>
                <h3 style="margin:0 0 6px">Valider le défi</h3>
                <p id="validation-texte" class="muted" style="margin:0 0 16px"></p>
                <button class="btn btn-primary btn-block" onclick="valider()">✅ Valider la réalisation</button>
                <button class="btn btn-danger btn-block mt8" id="btn-invalider" style="display:none" onclick="invalider()">🤥 Invalider — ce n'est pas la vérité</button>
            </div>
        </div>

        <button class="btn btn-ghost btn-block mt16" onclick="terminerPartie()">🏁 Terminer la partie</button>

        <div class="card mt16 pad-sm" id="log-box" style="display:none">
            <div class="section-head" style="margin-top:0"><h2 style="font-size:15px">Derniers événements</h2></div>
            <div id="dernier-tour" style="font-size:14px"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const partieUrl = @json(url('jeux/verite-action/'.$partie->id));

        function updateScores(scores) {
            const strip = document.getElementById('score-strip');
            const p1 = scores.joueur1, p2 = scores.joueur2;
            const el1 = strip.querySelector('[data-player="1"] .pts');
            const el2 = strip.querySelector('[data-player="2"] .pts');
            if (p1.name) el1.textContent = p1.score;
            if (p2.name) el2.textContent = p2.score;
        }

        let lastTourId = 0;
        let gagePinned = false;
        let currentCarteId = 0;

        function showStage(stage, state) {
            ['choix','carte','gage','validation'].forEach(s => document.getElementById('stage-' + s).style.display = 'none');
            document.getElementById('loading').style.display = 'none';
            document.getElementById('waiting-hint').style.display = 'none';

            if (stage === 'choix') {
                document.getElementById('stage-choix').style.display = 'block';
                document.getElementById('stage-title-choix').textContent = state.estActif ? 'À toi de jouer !' : 'En attente…';
            } else if (stage === 'carte') {
                document.getElementById('stage-carte').style.display = 'block';
            } else if (stage === 'validation') {
                document.getElementById('stage-validation').style.display = 'block';
                document.getElementById('btn-invalider').style.display = state.carte && state.carte.type === 'verite' ? 'block' : 'none';
                if (state.carte) {
                    let content = `Ton/ta partenaire a réalisé : « ${state.carte.texte} »`;
                    if (state.carte.reponse) {
                        content += `<div class="tiny mt8" style="font-style:italic">Sa réponse : « ${state.carte.reponse} »</div>`;
                    }
                    document.getElementById('validation-texte').innerHTML = content;
                } else {
                    document.getElementById('validation-texte').textContent = 'Ton/ta partenaire a réalisé son défi.';
                }
            }
        }

        function render(state) {
            const tourId = (state.carte && state.carte.id) || (state.dernierEvenement && state.dernierEvenement.id) || 0;

            if (tourId < lastTourId) return;
            lastTourId = Math.max(lastTourId, tourId);

            if (gagePinned) {
                const nouveauTour = state.stage === 'choix' && state.estActif;
                if (state.status === 'en_cours' && !nouveauTour) return;
                gagePinned = false;
            }

            updateScores(state.scores);

            const box = document.getElementById('dernier-tour');
            if (state.dernierTour) {
                const e = document.getElementById('log-box');
                e.style.display = 'block';
                const d = state.dernierTour;
                const icone = d.type === 'verite' ? '💬' : (d.type === 'action' ? '🎬' : '😅');
                const accepte = d.accepte ? '✅' : '🙅';
                box.innerHTML = `<div class="row"><span>${icone}</span><div class="grow"><div>${d.joueur} : « ${d.texte} »</div><small>${accepte} ${d.points >= 0 ? '+' : ''}${d.points} pts</small></div></div>`;
            }

            if (state.status !== 'en_cours') {
                document.getElementById('game-zone').innerHTML = `
                    <div style="font-size:44px; margin-bottom:8px">🏁</div>
                    <h2>Partie terminée !</h2>
                    <p class="muted" style="margin:8px 0 18px">Revenez à la page d'accueil du jeu pour voir le score final.</p>
                    <a href="${partieUrl.replace('jwhs', '')}" onclick="return false" style="display:none"></a>
                    <a class="btn btn-primary" href="/jeux/verite-action">Retour au jeu</a>
                `;
                return;
            }

            if (state.stage === 'carte' && state.carte) {
                showStage('carte', state);
                const estVerite = state.carte.type === 'verite';
                document.getElementById('carte-ico').textContent = estVerite ? '💬' : '🎬';
                document.getElementById('carte-type').textContent = estVerite ? 'Vérité' : 'Action';
                document.getElementById('carte-texte').textContent = state.carte.texte;
                document.getElementById('carte-actions').style.display = estVerite ? 'none' : 'grid';
                document.getElementById('verite-answer').style.display = estVerite ? 'block' : 'none';
                if (state.carte.id !== currentCarteId) {
                    currentCarteId = state.carte.id;
                    document.getElementById('verite-reponse').value = '';
                }
            } else if (state.stage === 'validation') {
                showStage('validation', state);
            } else if (state.stage === 'choix') {
                showStage('choix', state);
            } else {
                showStage('attente', state);
                document.getElementById('waiting-hint').style.display = 'block';
                const attendValidation = state.dernierEvenement && state.dernierEvenement.statut === 'realise';
                document.getElementById('waiting-hint').textContent = attendValidation
                    ? 'En attente de la validation du partenaire…'
                    : (state.estActif
                        ? 'À toi de jouer…'
                        : `En attente de ${state.joueurActif ? state.joueurActif.name : 'ton/ta partenaire'}…`);
            }
        }

        async function refreshEtat() {
            const res = await api(partieUrl + '/etat', { json: false });
            if (res.ok) render(res.data);
        }

        function choisir(type) {
            api(partieUrl + '/choisir', { method: 'POST', body: { type: type } }).then(res => {
                if (res.ok) {
                    lastTourId = res.data.tour_id || lastTourId;
                    refreshEtat();
                }
            });
        }

        async function envoyerVerite() {
            const texte = document.getElementById('verite-reponse').value.trim();
            if (!texte) {
                toast('Écris d\'abord ta vérité !', 'info');
                return;
            }
            await repondre(true, texte);
        }

        async function repondre(accepte, reponse) {
            const body = { accepte: accepte ? 1 : 0 };
            if (reponse) body.reponse = reponse;
            const res = await api(partieUrl + '/repondre', { method: 'POST', body: body });
            if (res.ok) {
                if (!accepte && res.data.gage) {
                    gagePinned = true;
                    document.getElementById('gage-texte').textContent = '« ' + res.data.gage + ' »';
                    document.getElementById('stage-carte').style.display = 'none';
                    document.getElementById('stage-gage').style.display = 'block';
                }
                toast(res.data.message, res.data.statut === 'refuse' ? 'info' : 'success');
            }
        }

        async function valider() {
            const res = await api(partieUrl + '/valider', { method: 'POST' });
            if (res.ok) toast(res.data.message, 'success');
        }

        async function invalider() {
            if (!confirm('Es-tu sûr·e ? Cette vérité sera rejetée sans point.')) return;
            const res = await api(partieUrl + '/invalider', { method: 'POST' });
            if (res.ok) toast(res.data.message, 'info');
        }

        async function terminerPartie() {
            const res = await api(partieUrl + '/terminer', { method: 'POST' });
            if (res.ok && res.data.redirect) window.location.href = res.data.redirect;
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(partieUrl + '/etat', render, { interval: 1800 });
        });
    </script>
@endpush