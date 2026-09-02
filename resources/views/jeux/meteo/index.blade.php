@extends('layouts.app')

@section('title', 'Météo du Couple')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🌦️</div>
            <h1 class="title">Météo du Couple</h1>
            <p class="subtitle">Un check-in d'émotions quotidien : partagez votre humeur, recevez une alerte si vous allez mal tous les deux, et suivez vos tendances.</p>
        </div>

        <div class="center mt8">
            <span class="badge neutre" id="meteo-date">…</span>
        </div>

        <div class="card mt16" style="border-color:rgba(45,212,191,.4)">
            <div id="meteo-zone">
                <div class="center" style="padding:40px"><div class="spinner"></div></div>
            </div>
        </div>

        <section class="section-head"><h2>Tendances</h2><span class="tiny muted">14 derniers jours</span></section>
        <div class="card pad-sm">
            <div id="meteo-trend">
                <div class="center" style="padding:40px"><div class="spinner"></div></div>
            </div>
            <div class="mt8" style="display:flex;gap:8px;flex-wrap:wrap">
                <span class="tiny" style="display:inline-flex;align-items:center;gap:4px"><span class="m-emo" style="width:14px;height:14px;background:rgba(53,208,127,.2);display:inline-flex;border-radius:4px"></span> Beau temps</span>
                <span class="tiny" style="display:inline-flex;align-items:center;gap:4px"><span class="m-emo" style="width:14px;height:14px;background:rgba(255,176,32,.18);display:inline-flex;border-radius:4px"></span> Mitigé</span>
                <span class="tiny" style="display:inline-flex;align-items:center;gap:4px"><span class="m-emo" style="width:14px;height:14px;background:rgba(230,57,70,.22);display:inline-flex;border-radius:4px"></span> Mauvais temps</span>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const meteoUrl = @json(url('jeux/meteo-du-couple'));
        let meteoData = null;

        function moodEmoji(key, metas) { return metas[key]?.emoji ?? ''; }
        function moodLabel(key, metas) { return metas[key]?.label ?? ''; }
        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function renderMeteo() {
            const d = meteoData;
            if (!d) return;
            const zone = document.getElementById('meteo-zone');
            const dateEl = document.getElementById('meteo-date');

            const draftCom = zone.querySelector('#meteo-commentaire')?.value ?? '';
            const draftSug = zone.querySelector('#meteo-suggestion')?.value ?? '';
            const wasFocused = document.activeElement && zone.contains(document.activeElement);
            const selectedMood = zone.querySelector('input[name="humeur"]:checked')?.value ?? null;

            dateEl.textContent = '📅 ' + d.jour;

            if (!d.jaiRepondu) {
                zone.innerHTML = `
                    <div class="tiny muted mb8">Comment te sens-tu aujourd'hui (1er partage) ?</div>
                    <div class="mood-grid">
                        ${Object.entries(d.meteos).map(([k, m]) => `
                            <label class="mood-btn">
                                <input type="radio" name="humeur" class="mood-${k}" value="${k}" ${k === selectedMood ? 'checked' : ''}>
                                <span><span>${m.emoji}</span><b>${m.label}</b></span>
                            </label>`).join('')}
                    </div>
                    <input class="input mt12" id="meteo-commentaire" maxlength="255" placeholder="Un petit mot, en option…">
                    <button class="btn btn-primary btn-block mt8" onclick="enregistrerMeteo()">😊 Envoyer mon humeur</button>`;
            } else if (d.mesPartages.length < d.maxPartages) {
                zone.innerHTML = `
                    <div class="chip">Ton humeur : <b style="margin-left:4px">${moodEmoji(d.maHumeur, d.meteos)} ${moodLabel(d.maHumeur, d.meteos)}</b></div>
                    <div class="divider"></div>
                    <div class="tiny muted mb8">Partage encore une fois ton humeur (message du soir) :</div>
                    <div class="mood-grid">
                        ${Object.entries(d.meteos).map(([k, m]) => `
                            <label class="mood-btn">
                                <input type="radio" name="humeur" class="mood-${k}" value="${k}" ${k === selectedMood ? 'checked' : ''}>
                                <span><span>${m.emoji}</span><b>${m.label}</b></span>
                            </label>`).join('')}
                    </div>
                    <input class="input mt12" id="meteo-commentaire" maxlength="255" placeholder="Un petit mot, en option…">
                    <button class="btn btn-primary btn-block mt8" onclick="enregistrerMeteo()">🌙 Partager mon humeur du soir</button>
                    ${!d.ilElleARepondu ? `<div class="badge neutre mt12">⏳ En attente de l'humeur de ${d.partenaire}…</div>` : ''}
                    ${renderSuggestion(d)}`;
            } else {
                const syn = d.synthese ?? { emoji: '🌦️', label: 'Un jour partagé' };
                let alertHtml = '';
                if (d.lesDeuxMauvais) {
                    alertHtml = `
                        <div class="meteo-alert">
                            <div class="flex items-center gap8">
                                <span style="font-size:26px">⚠️</span>
                                <div><strong>Alerte météo</strong><div class="tiny">Vous vous sentez tous les deux mal en ce moment…</div></div>
                            </div>
                            <p class="mt8" style="font-size:14px;line-height:1.6">${d.suggestionReconfort}</p>
                        </div>`;
                }
                const mesComm = d.monCommentaire ? `<div class="tiny muted mt8">« ${esc(d.monCommentaire)} »</div>` : '';
                const saComm = d.saCommentaire ? `<div class="tiny muted mt8">« ${esc(d.saCommentaire)} »</div>` : '';

                zone.innerHTML = `
                    ${alertHtml}
                    <div class="card" style="background:linear-gradient(150deg, rgba(53,208,127,.12), rgba(77,171,247,.08)), var(--card)">
                        <div class="center">
                            <div style="font-size:42px">${syn.emoji}</div>
                            <div style="font-weight:700">${syn.label}</div>
                        </div>
                    </div>
                    <div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap">
                        <span class="chip">Toi : <b style="margin-left:4px">${moodEmoji(d.maHumeur, d.meteos)} ${moodLabel(d.maHumeur, d.meteos)}</b></span>
                        <span class="chip">${d.partenaire} : <b style="margin-left:4px">${moodEmoji(d.saHumeur, d.meteos)} ${moodLabel(d.saHumeur, d.meteos)}</b></span>
                    </div>
                    ${mesComm}${saComm}
                    ${renderSuggestion(d)}`;
            }

            const commEl = document.getElementById('meteo-commentaire');
            if (commEl && draftCom) commEl.value = draftCom;
            const sugEl = document.getElementById('meteo-suggestion');
            if (sugEl && draftSug) sugEl.value = draftSug;
            const focusEl = document.getElementById('meteo-commentaire') || document.getElementById('meteo-suggestion');
            if (wasFocused && focusEl) focusEl.focus();
        }

        function renderSuggestion(d) {
            const maIdee = d.maSuggestion
                ? `<span class="chip mt8">Ton idée : <b style="margin-left:4px">${esc(d.maSuggestion)}</b></span>`
                : `<div class="flex gap8 mt8">
                    <input class="input" id="meteo-suggestion" maxlength="280" placeholder="Écris ton idée pour embellir le ciel…">
                    <button class="btn btn-sm btn-primary" onclick="partagerSuggestion()">Partager</button>
                </div>`;
            const saIdee = d.saSuggestion
                ? `<span class="chip mt8">${d.partenaire} : <b style="margin-left:4px">${esc(d.saSuggestion)}</b></span>`
                : '';
            return `
                <div class="card pad-sm mt12">
                    <div class="tiny muted mb4">💡 Idée pour embellir le ciel</div>
                    <div style="font-size:14px; line-height:1.6">${d.suggestion}</div>
                    <div class="divider"></div>
                    <div class="flex gap8" style="flex-wrap:wrap">
                        ${maIdee}${saIdee}
                    </div>
                </div>`;
        }

        function renderTrend() {
            const d = meteoData;
            const trend = document.getElementById('meteo-trend');
            if (!d) return;
            if (!d.historique.length) {
                trend.innerHTML = '<div class="tiny muted center" style="padding:12px">Commencez à partager vos émotions pour voir la tendance du couple !</div>';
                return;
            }
            trend.innerHTML = `
                <div class="meteo-chart">
                    ${d.historique.map(day => `
                        <div class="mcell">
                            <span class="m-emo ${day.moi[0] ? d.meteos[day.moi[0].humeur].niveau : 'vide'}">${day.moi[0] ? moodEmoji(day.moi[0].humeur, d.meteos) : '·'}</span>
                            <span class="m-emo ${day.moi[1] ? d.meteos[day.moi[1].humeur].niveau : 'vide'}">${day.moi[1] ? moodEmoji(day.moi[1].humeur, d.meteos) : '·'}</span>
                            <span class="m-emo ${day.lui[0] ? d.meteos[day.lui[0].humeur].niveau : 'vide'}">${day.lui[0] ? moodEmoji(day.lui[0].humeur, d.meteos) : '·'}</span>
                            <span class="m-emo ${day.lui[1] ? d.meteos[day.lui[1].humeur].niveau : 'vide'}">${day.lui[1] ? moodEmoji(day.lui[1].humeur, d.meteos) : '·'}</span>
                            <span class="m-date">${day.jour}</span>
                        </div>`).join('')}
                </div>`;
        }

        function renderAll() {
            renderMeteo();
            renderTrend();
        }

        async function partagerSuggestion() {
            const input = document.getElementById('meteo-suggestion');
            const text = input?.value.trim() ?? '';
            if (!text) {
                toast('Écris d\'abord ton idée 😊', 'error');
                return;
            }
            const res = await api(meteoUrl + '/suggestion', {
                method: 'POST',
                body: { suggestion: text },
            });
            if (res.ok) {
                toast('Ton idée est partagée !', 'success');
                refresh();
            }
        }

        async function enregistrerMeteo() {
            const mood = document.querySelector('input[name="humeur"]:checked');
            if (!mood) {
                toast('Choisis une émotion 😊', 'error');
                return;
            }
            const comm = document.getElementById('meteo-commentaire')?.value ?? '';
            const res = await api(meteoUrl + '/checkin', {
                method: 'POST',
                body: { humeur: mood.value, commentaire: comm },
            });
            if (res.ok) {
                toast('Humeur partagée !', 'success');
                refresh();
            }
        }

        async function refresh() {
            const res = await api(meteoUrl + '/etat', { json: false });
            if (res.ok) {
                meteoData = res.data;
                renderAll();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(meteoUrl + '/etat', (data) => { meteoData = data; renderAll(); }, { interval: 3000 });
        });
    </script>
@endpush