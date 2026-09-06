@extends('layouts.app')

@section('title', 'Calendrier du Couple')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🗓️</div>
            <h1 class="title">Calendrier du Couple</h1>
            <p class="subtitle">L'emploi du temps de chacun, jour par jour, heure par heure, côte à côte.</p>
        </div>

        <div class="card pad-sm mt16">
            <div class="cal-header">
                <button type="button" class="cal-nav" onclick="calDeplacer(-1)" aria-label="Jour précédent">‹</button>
                <div class="grow center">
                    <div id="cal-date" class="cal-date">…</div>
                    <button type="button" class="btn btn-sm btn-soft mt4" onclick="calOuvrirMini()">📅 Choisir une date</button>
                </div>
                <button type="button" class="cal-nav" onclick="calDeplacer(1)" aria-label="Jour suivant">›</button>
            </div>
            <div class="center mt8">
                <button type="button" class="btn btn-sm btn-ghost" onclick="calAujourdhui()">⚡ Aujourd'hui</button>
            </div>
        </div>

        {{-- Mini-calendrier de sélection de date --}}
        <div id="cal-mini" class="modal-ov" style="display:none" onclick="if(event.target===this) calFermerMini()">
            <div class="modal center">
                <h3>Choisir une date</h3>
                <input type="date" class="input mt8" id="cal-mini-input">
                <div class="grid2 mt16">
                    <button class="btn btn-ghost" onclick="calFermerMini()">Annuler</button>
                    <button class="btn btn-primary" onclick="calValiderMini()">Aller</button>
                </div>
            </div>
        </div>

        <div class="card mt16 pad-sm">
            <div id="cal-grid">
                <div class="center" style="padding:30px"><div class="spinner"></div></div>
            </div>
        </div>
    </div>

    {{-- Modal créneau --}}
    <div id="cal-modal" class="modal-ov" style="display:none" onclick="if(event.target===this) calFermer()">
        <div class="modal">
            <h3 id="cal-modal-title">Nouvelle activité</h3>
            <div id="cal-detail" style="display:none">
                <p class="cal-detail-heures" id="cal-detail-heures">…</p>
                <p class="tiny muted mt4" id="cal-detail-titre">…</p>
                <div class="cal-detail-row" id="cal-detail-raison" style="display:none">
                    <span>🏷️</span>
                    <span id="cal-detail-raison-libelle"></span>
                </div>
                <p class="tiny muted mt8" id="cal-detail-par">…</p>
                <div class="mt16">
                    <button type="button" class="btn btn-primary" onclick="calFermer()">Fermer</button>
                </div>
            </div>
            <form id="cal-editor" onsubmit="event.preventDefault(); calSauver();">
                <label class="label">Titre</label>
                <input class="input mb8" id="cal-titre" maxlength="255" required>
                <div class="grid2">
                    <div>
                        <label class="label">Début</label>
                        <input type="time" class="input" id="cal-debut" required>
                    </div>
                    <div>
                        <label class="label">Fin (optionnelle)</label>
                        <input type="time" class="input" id="cal-fin">
                    </div>
                </div>
                <label class="label">Raison <span class="tiny muted">(optionnel)</span></label>
                <input class="input mb8 mt4" id="cal-raison" maxlength="255" placeholder="Ex : Sport, Rendez-vous, Repos…">
                <label class="label">Couleur <span class="tiny muted">(optionnel, par défaut <span class="cal-dot c-{{ \App\Http\Controllers\CalendrierController::DEFAULT_COULEUR }}"></span>)</span></label>
                <div class="cal-swatches mt8" id="cal-swatches">
                    @foreach ($couleurs as $c)
                        <button type="button" class="cal-swatch c-{{ $c }} {{ $c === \App\Http\Controllers\CalendrierController::DEFAULT_COULEUR ? 'on' : '' }}" data-v="{{ $c }}" aria-label="{{ $c }}"></button>
                    @endforeach
                </div>
                <div class="cal-actions mt16">
                    <button type="button" class="btn btn-danger" id="cal-suppr" style="display:none" onclick="calSupprimer()">Supprimer</button>
                    <button type="submit" class="btn btn-primary" id="cal-save">
                        <span class="spinner cal-save-spinner" id="cal-save-spinner" style="display:none"></span>
                        <span id="cal-save-label">Enregistrer</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const calStateUrl = '{{ route("calendrier.state") }}';
        const calBaseUrl = @json(url('/jeux/calendrier'));
        const HOUR_PX = 58;
        const HOURS = 24;
        let calJour = null;          // 'YYYY-MM-DD'
        let calCreneaux = [];
        let calEditeId = null;
        let calSaving = false;
        let calCouleur = @json(\App\Http\Controllers\CalendrierController::DEFAULT_COULEUR);
        const calMeId = {{ Auth::id() }};
        const calPartnerId = @json($couple->partnerOf(Auth::user())->id);
        const CAL_COULEURS = @json($couleurs);
        const CAL_DEFAUT = @json(\App\Http\Controllers\CalendrierController::DEFAULT_COULEUR);

        function calEsc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function calFmtDate(d) {
            const mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
            const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
            const [y, m, dd] = d.split('-').map(Number);
            const date = new Date(y, m - 1, dd);
            return jours[date.getDay()] + ' ' + dd + ' ' + mois[m - 1];
        }

        function calParse(jourStr) {
            const [y, m, d] = jourStr.split('-').map(Number);
            return new Date(y, m - 1, d);
        }
        function calToStr(dt) {
            return dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
        }

        function calMin(s) {
            const [h, m] = s.split(':').map(Number);
            return h * 60 + m;
        }

        // Règle « En cours » : un créneau sans fin s'étire jusque
        //   - au début du créneau suivant du même utilisateur,
        //   - sinon à l'heure actuelle si l'utilisateur est encore dans la journée,
        //   - sinon il reste « ouvert » affiché sans fond/contour du bas.
        function calFinEffective(c, userCreneaux) {
            if (c.heure_fin) return calMin(c.heure_fin);
            const debut = calMin(c.heure_debut);
            const suivants = userCreneaux
                .filter(x => x.id !== c.id && calMin(x.heure_debut) > debut)
                .sort((a, b) => calMin(a.heure_debut) - calMin(b.heure_debut));
            if (suivants.length) return Math.min(calMin(suivants[0].heure_debut), 24 * 60);
            // Dernier créneau de son utilisateur sans fin.
            return null;
        }

        function calTodayStr() {
            const n = new Date();
            return calToStr(n);
        }
        function calNowMin() {
            const n = new Date();
            return n.getHours() * 60 + n.getMinutes();
        }

        function calRender() {
            const box = document.getElementById('cal-grid');
            document.getElementById('cal-date').textContent = calFmtDate(calJour);

            const mine = calCreneaux.filter(c => c.user_id === calMeId);
            const sien = calCreneaux.filter(c => c.user_id === calPartnerId);

            const colItems = (list) => list.map(c => {
                const debut = calMin(c.heure_debut);
                let fin = calFinEffective(c, list);
                let enCours = false;
                if (fin === null) {
                    // Activité « ouverte » : s'étire à l'heure actuelle si c'est
                    // le créneau en cours aujourd'hui, sinon jusqu'en bas visible.
                    const estAujourdhui = calJour === calTodayStr();
                    fin = Math.max(debut + 30, Math.min(estAujourdhui ? calNowMin() : 24 * 60, 24 * 60));
                    if (estAujourdhui && calNowMin() >= debut) enCours = true;
                }
                const top = (debut / 60) * HOUR_PX;
                const hauteur = Math.max(30, ((fin - debut) / 60) * HOUR_PX);
                const raison = c.raison ? `<span class="cal-block-raison">🏷️ ${calEsc(c.raison)}</span>` : '';
                return `
                    <button type="button" class="cal-block ${c.couleur ? 'c-' + c.couleur : ''}"
                            style="top:${top}px; min-height:${hauteur}px; right:6px; left:6px;"
                            onclick="calOuvrir(${JSON.stringify(c.id)})">
                        <span class="cal-block-time">${c.heure_debut}${c.heure_fin ? '–' + c.heure_fin : ''}</span>
                        <span class="cal-block-titre">${calEsc(c.titre)}${enCours ? ' <em>En cours</em>' : ''}</span>
                        ${raison}
                    </button>`;
            }).join('');

            // Barres horaires + colonnes
            const heures = Array.from({ length: HOURS + 1 }, (_, h) =>
                `<div class="cal-hour" style="top:${(h * HOUR_PX)}px"><span>${String(h).padStart(2, '0')}:00</span></div>`
            ).join('');

            const nomMoi = 'Toi';
            const nomToi = @json($couple->partnerOf(Auth::user())->name);

            box.innerHTML = `
                <div class="cal-row-head">
                    <div class="cal-col-head" style="color:var(--primary)">🧑 ${calEsc(nomMoi)}</div>
                    <div class="cal-col-head" style="color:var(--primary)">🧑 ${calEsc(nomToi)}</div>
                </div>
                <div class="cal-timeline">
                    <div class="cal-labels">${heures}</div>
                    <div class="cal-lane">${colItems(mine)}</div>
                    <div class="cal-lane">${colItems(sien)}</div>
                    ${(calJour === calTodayStr()) ? `<div class="cal-now" style="top:${(calNowMin() / 60) * HOUR_PX}px"><div class="cal-now-dot"></div></div>` : ''}
                </div>
                <div class="tiny muted center mt8">Cliquez sur une colonne à l'heure voulue pour ajouter un créneau.</div>`;
        }

        async function calCharger() {
            const res = await api(calStateUrl + '?date=' + calJour, { json: false });
            if (res.ok) {
                calCreneaux = res.data.creneaux;
                calRender();
            }
        }

        function calDeplacer(n) {
            const d = calParse(calJour);
            d.setDate(d.getDate() + n);
            calJour = calToStr(d);
            calCharger();
        }

        function calAujourdhui() {
            calJour = calTodayStr();
            calCharger();
        }

        function calOuvrirMini() {
            document.getElementById('cal-mini-input').value = calJour;
            document.getElementById('cal-mini').style.display = 'flex';
        }
        function calFermerMini() {
            document.getElementById('cal-mini').style.display = 'none';
        }
        function calValiderMini() {
            const v = document.getElementById('cal-mini-input').value;
            if (v) { calJour = v; calCharger(); }
            calFermerMini();
        }

        function calOuvrir(id) {
            const c = calCreneaux.find(x => x.id === id);
            if (!c) return;
            if (c.user_id === calMeId) {
                calOuvrirEdition(c);
            } else {
                calOuvrirDetail(c);
            }
        }

        // Propriétaire : formulaire d'édition.
        function calOuvrirEdition(c) {
            calEditeId = c.id;
            document.getElementById('cal-detail').style.display = 'none';
            document.getElementById('cal-editor').style.display = '';
            document.getElementById('cal-modal-title').textContent = 'Modifier l\'activité';
            document.getElementById('cal-titre').value = c.titre;
            document.getElementById('cal-debut').value = (c.heure_debut || '').slice(0, 5);
            document.getElementById('cal-fin').value = (c.heure_fin || '').slice(0, 5);
            document.getElementById('cal-raison').value = c.raison || '';
            document.querySelectorAll('#cal-swatches .cal-swatch').forEach(s => s.classList.toggle('on', s.dataset.v === (c.couleur || CAL_DEFAUT)));
            calCouleur = c.couleur || CAL_DEFAUT;
            document.getElementById('cal-suppr').style.display = '';
            document.getElementById('cal-modal').style.display = 'flex';
        }

        // L'autre : lecture seule, tous les détails visibles.
        function calOuvrirDetail(c) {
            calEditeId = null;
            document.getElementById('cal-editor').style.display = 'none';
            document.getElementById('cal-detail').style.display = '';
            document.getElementById('cal-modal-title').textContent = calEsc(c.titre);
            document.getElementById('cal-detail-heures').textContent = c.heure_debut + (c.heure_fin ? ' – ' + c.heure_fin : ' → en cours');
            document.getElementById('cal-detail-titre').textContent = calEsc(c.titre);
            const raisonWrap = document.getElementById('cal-detail-raison');
            if (c.raison) {
                raisonWrap.style.display = 'flex';
                document.getElementById('cal-detail-raison-libelle').textContent = c.raison;
            } else {
                raisonWrap.style.display = 'none';
            }
            document.getElementById('cal-detail-par').textContent = 'Ajouté par ' + (c.user_name || 'votre moitié');
            document.getElementById('cal-detail-par').textContent = 'Ajouté par ' + (c.user_name || 'votre moitié');
            document.getElementById('cal-modal').style.display = 'flex';
        }

        window.calAjouter = function (heures) {
            calEditeId = null;
            document.getElementById('cal-detail').style.display = 'none';
            document.getElementById('cal-editor').style.display = '';
            document.getElementById('cal-modal-title').textContent = 'Nouvelle activité';
            document.getElementById('cal-titre').value = '';
            document.getElementById('cal-debut').value = heures;
            document.getElementById('cal-fin').value = '';
            document.getElementById('cal-raison').value = '';
            document.querySelectorAll('#cal-swatches .cal-swatch').forEach(s => s.classList.toggle('on', s.dataset.v === CAL_DEFAUT));
            calCouleur = CAL_DEFAUT;
            document.getElementById('cal-suppr').style.display = 'none';
            document.getElementById('cal-modal').style.display = 'flex';
        };

        function calFermer() {
            document.getElementById('cal-modal').style.display = 'none';
            calEditeId = null;
        }

        async function calSauver() {
            const titre = document.getElementById('cal-titre').value.trim();
            const debut = document.getElementById('cal-debut').value;
            const fin = document.getElementById('cal-fin').value || null;
            const raison = document.getElementById('cal-raison').value.trim() || null;
            const couleur = calCouleur || CAL_DEFAUT;
            if (!titre || !debut || calSaving) return;

            const etaitModif = Boolean(calEditeId);
            calSaving = true;
            const saveBtn = document.getElementById('cal-save');
            saveBtn.disabled = true;
            const saveSpinner = document.getElementById('cal-save-spinner');
            if (saveSpinner) saveSpinner.style.display = 'inline-block';
            let res;
            try {
                if (etaitModif) {
                    res = await api(calBaseUrl + '/' + calEditeId, { method: 'PUT', body: { titre, raison, heure_debut: debut, heure_fin: fin, couleur } });
                } else {
                    res = await api(calBaseUrl + '/creer', { method: 'POST', body: { date: calJour, titre, raison, heure_debut: debut, heure_fin: fin, couleur } });
                }
            } finally {
                calSaving = false;
                saveBtn.disabled = false;
                if (saveSpinner) saveSpinner.style.display = 'none';
            }
            if (res.ok) {
                calFermer();
                await calCharger();
                toast(etaitModif ? 'Activité modifiée ✏️' : 'Activité ajoutée 🗓️', 'success');
            }
        }

        async function calSupprimer() {
            if (!calEditeId) return;
            if (!confirm('Supprimer ce créneau ?')) return;
            const res = await api(calBaseUrl + '/' + calEditeId, { method: 'DELETE' });
            if (res.ok) {
                calFermer();
                await calCharger();
                toast('Créneau supprimé 🗑️', 'info');
            }
        }

        document.querySelectorAll('#cal-swatches .cal-swatch').forEach(s => s.addEventListener('click', () => {
            document.querySelectorAll('#cal-swatches .cal-swatch').forEach(x => x.classList.remove('on'));
            s.classList.add('on');
            calCouleur = s.dataset.v;
        }));

        // Clic sur une colonne/label pour créer un créneau à cette heure.
        document.addEventListener('click', (e) => {
            const lane = e.target.closest('.cal-lane');
            if (!lane) return;
            if (e.target.closest('.cal-block')) return;
            const rect = lane.getBoundingClientRect();
            const off = e.clientY - rect.top;
            if (off < 0) return;
            const minutes = Math.floor(off / HOUR_PX * 60);
            const h = Math.max(0, Math.min(23, Math.floor(minutes / 60)));
            const m = 0;
            calAjouter(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
        });

        document.addEventListener('DOMContentLoaded', () => {
            calJour = calTodayStr();
            startPolling(calStateUrl + '?date=' + calJour, (data) => {
                // On ne met à jour que si on regarde toujours le même jour.
                if (data.date === calJour) {
                    calCreneaux = data.creneaux;
                    calRender();
                }
            }, { interval: 1600 });
        });
    </script>
@endpush
