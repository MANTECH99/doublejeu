@extends('layouts.app')

@section('title', 'Bucket List du Couple')

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🧳</div>
            <h1 class="title">Bucket List du Couple</h1>
            <p class="subtitle">Toutes vos idées d'expériences, classées par thèmes. Réalisez-les, puis gardez-en la photo souvenir.</p>
        </div>

        {{-- Ajout rapide --}}
        <section class="section-head"><h2>➕ Ajouter une activité</h2></section>
        <div class="card pad-sm">
            <form id="bl-form">
                <div class="grid2">
                    <input class="input" id="bl-titre" maxlength="255" placeholder="Ex : Week-end à Rome" required>
                    <input class="input" id="bl-lieu" maxlength="255" placeholder="Lieu (optionnel)">
                </div>
                <div class="pill-grid mt8" id="bl-cats">
                    @foreach ($categories as $val => $label)
                        <label class="pill-cat" data-v="{{ $val }}">
                            <input type="radio" name="categorie" value="{{ $val }}" {{ $loop->first ? 'checked' : '' }}>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="btn btn-primary btn-block mt8" type="submit">Ajouter à ma liste ➕</button>
            </form>
        </div>

        {{-- Onglets à réaliser / réalisées --}}
        <div class="bl-tabs">
            <button type="button" class="bl-tab active" data-tab="a-faire">✅ À réaliser <span class="bl-count" id="bl-count-a-faire">0</span></button>
            <button type="button" class="bl-tab" data-tab="faites">📸 Réalisées <span class="bl-count" id="bl-count-faites">0</span></button>
        </div>

        {{-- Filtre par catégorie --}}
        <div class="bl-filters" id="bl-filters">
            <button type="button" class="bl-filter active" data-f="tous">Tout</button>
            @foreach ($categories as $val => $label)
                <button type="button" class="bl-filter" data-f="{{ $val }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="card pad-sm mt16" id="bl-list">
            <div class="center" style="padding:30px"><div class="spinner"></div></div>
        </div>

        <section class="section-head"><h2>📸 Album Souvenir</h2><span class="tiny muted">Les photos des activités réalisées, dans l'ordre</span></section>
        <div class="card pad-sm mt16" id="bl-album">
            <div class="tiny muted center" style="padding:16px">Aucun souvenir pour l'instant. Cochez une activité et ajoutez sa photo !</div>
        </div>
    </div>

    {{-- Modal photo --}}
    <div id="bl-photo-modal" class="modal-ov" style="display:none" onclick="if(event.target===this) fermerBlPhoto()">
        <div class="modal center">
            <div style="font-size:40px">📸</div>
            <h3 style="margin-top:10px">Ajouter un souvenir</h3>
            <p class="muted" id="bl-photo-titre"></p>
            <input type="file" class="input mt8" id="bl-photo-input" accept="image/*" capture="environment">
            <div class="grid2 mt16">
                <button class="btn btn-ghost" onclick="fermerBlPhoto()">Annuler</button>
                <button class="btn btn-primary" id="bl-photo-upload" onclick="envoyerBlPhoto()">Enregistrer</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const blStateUrl = '{{ route("bucket-list.state") }}';
        const blCreerUrl = '{{ route("bucket-list.creer") }}';
        let blItems = [];
        let blTab = 'a-faire';
        let blFiltre = 'tous';
        let blPhotoCibleId = null;

        const BL_CATS = @json($categories);
        const BL_ICONS = @json(\App\Http\Controllers\BucketListController::CATEGORIES_ICONS);

        function blEsc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function blParTitre(titre) {
            const c = (titre || '').toLowerCase();
            let base = 'var(--primary)';
            if (c.includes('week') || c.includes('voyage') || c.includes('rome') || c.includes('plage')) base = '#0ea5e9';
            return base;
        }

        function blItemHtml(it) {
            const ico = BL_ICONS[it.categorie] || '⭐';
            const lieue = it.lieu ? `<div class="bl-lieu">📍 ${blEsc(it.lieu)}</div>` : '';
            const check = it.realise ? '✓' : '';
            return `
                <div class="bl-item ${it.realise ? 'done' : ''}" data-id="${it.id}">
                    <button class="bl-check ${it.realise ? 'done' : ''}" onclick="blBasculer(${it.id})" aria-label="${it.realise ? 'Réouvrir' : 'Marquer réalisé'}">${check}</button>
                    <div class="bl-main">
                        <div class="bl-titre">${blEsc(it.titre)} ${it.cree_par ? `<span class="bl-cree">par ${blEsc(it.cree_par)}</span>` : ''}</div>
                        ${lieue}
                        <div class="bl-meta"><span class="bl-cat">${ico} ${blEsc(BL_CATS[it.categorie] || it.categorie)}</span></div>
                    </div>
                    <div class="bl-actions">
                        ${it.realise && !!it.photos.length ? `<button class="bl-mini" onclick="ouvrirBlAlbum()">📸</button>` : ''}
                        <button class="bl-mini" onclick="blSupprimer(${it.id})">🗑️</button>
                    </div>
                </div>`;
        }

        function blRender() {
            const visibles = blItems.filter(it =>
                (blTab === 'a-faire' ? !it.realise : it.realise) &&
                (blFiltre === 'tous' || it.categorie === blFiltre)
            );

            document.getElementById('bl-count-a-faire').textContent = blItems.filter(i => !i.realise).length;
            document.getElementById('bl-count-faites').textContent = blItems.filter(i => i.realise).length;

            const list = document.getElementById('bl-list');
            if (blTab === 'a-faire' && visibles.length === 0) {
                list.innerHTML = '<div class="center muted" style="padding:20px">Aucune idée pour l\'instant. Ajoutez-en une plus haut ! ☝️</div>';
            } else if (blTab === 'faites' && visibles.length === 0) {
                list.innerHTML = '<div class="center muted" style="padding:20px">Aucune activité réalisée pour le moment. À vous de cocher 🎯</div>';
            } else {
                list.innerHTML = visibles.map(blItemHtml).join('');
            }

            blAlbum();
        }

        function blAlbum() {
            const photos = blItems
                .filter(i => i.realise && i.photos.length)
                .sort((a, b) => (a.realise_at || '').localeCompare(b.realise_at || ''))
                .flatMap(i => i.photos.map(p => ({ p, t: i.titre })));
            const el = document.getElementById('bl-album');
            if (!photos.length) {
                el.innerHTML = '<div class="tiny muted center" style="padding:16px">Aucun souvenir pour l\'instant. Cochez une activité et ajoutez sa photo !</div>';
                return;
            }
            el.innerHTML = '<div class="bl-album">' + photos.map(({ p, t }) =>
                `<img class="bl-album-photo" src="${p}" alt="${blEsc(t)}" loading="lazy">`
            ).join('') + '</div>';
        }

        function blAlbumVoir(idx) {
            toast('Photo souvenir 📸', 'info');
        }

        async function blBasculer(id) {
            const it = blItems.find(i => i.id === id);
            if (!it) return;
            if (it.realise) {
                const res = await api(blStateUrl.replace('/etat', '') + '/' + id + '/reouvrir', { method: 'POST' });
                if (res.ok) {
                    blItems = blItems.map(i => i.id === id ? res.data.item : i);
                    blRender();
                    toast('Activité réouverte 🔄', 'info');
                }
            } else {
                const res = await api(blStateUrl.replace('/etat', '') + '/' + id + '/realiser', { method: 'POST' });
                if (res.ok) {
                    blItems = blItems.map(i => i.id === id ? res.data.item : i);
                    blRender();
                    toast('Activité réalisée 🎉', 'success');
                    if (res.data.item.photos.length === 0) {
                        ouvrirBlPhoto(id);
                    }
                }
            }
        }

        function ouvrirBlPhoto(id) {
            blPhotoCibleId = id;
            const it = blItems.find(i => i.id === id);
            document.getElementById('bl-photo-titre').textContent = it ? it.titre : '';
            document.getElementById('bl-photo-input').value = '';
            document.getElementById('bl-photo-modal').style.display = 'flex';
        }

        function fermerBlPhoto() {
            document.getElementById('bl-photo-modal').style.display = 'none';
            blPhotoCibleId = null;
        }

        async function envoyerBlPhoto() {
            if (!blPhotoCibleId) return;
            const input = document.getElementById('bl-photo-input');
            if (!input.files || !input.files[0]) {
                toast('Choisis une photo d\'abord', 'error');
                return;
            }
            const file = input.files[0];
            if (file.size > 10 * 1024 * 1024) {
                toast('Image trop lourde (max 10 Mo).', 'error');
                return;
            }
            const fd = new FormData();
            fd.append('photo', file);
            const btn = document.getElementById('bl-photo-upload');
            btn.disabled = true;
            try {
                const res = await fetch(blStateUrl.replace('/etat', '') + '/' + blPhotoCibleId + '/photo', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                if (res.ok) {
                    fermerBlPhoto();
                    await blRefresh();
                    blRender();
                    toast('Souvenir ajouté 📸', 'success');
                } else {
                    const data = await res.json().catch(() => ({}));
                    toast(data.error || 'Impossible d\'ajouter la photo.', 'error');
                }
            } catch (e) {
                toast('Connexion impossible. Vérifie ta connexion.', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        async function blSupprimer(id) {
            if (!confirm('Supprimer cette activité ?')) return;
            const res = await api(blStateUrl.replace('/etat', '') + '/' + id, { method: 'DELETE' });
            if (res.ok) {
                blItems = blItems.filter(i => i.id !== id);
                blRender();
                toast('Activité supprimée 🗑️', 'info');
            }
        }

        function ouvrirBlAlbum() {
            document.getElementById('bl-album').scrollIntoView({ behavior: 'smooth' });
        }

        async function blRefresh() {
            const res = await api(blStateUrl, { json: false });
            if (res.ok) {
                blItems = res.data.items;
                blRender();
            }
            return res.data;
        }

        document.getElementById('bl-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const titre = document.getElementById('bl-titre').value.trim();
            const lieu = document.getElementById('bl-lieu').value.trim();
            const categorie = document.querySelector('input[name="categorie"]:checked')?.value || 'voyages';
            if (!titre) return;
            const res = await api(blCreerUrl, { method: 'POST', body: { titre, lieu, categorie } });
            if (res.ok) {
                blItems.push(res.data.item);
                document.getElementById('bl-titre').value = '';
                document.getElementById('bl-lieu').value = '';
                blRender();
                toast('Idée ajoutée ✨', 'success');
            }
        });

        document.querySelectorAll('.bl-tab').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.bl-tab').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            blTab = b.dataset.tab;
            blRender();
        }));

        document.querySelectorAll('.bl-filter').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.bl-filter').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            blFiltre = b.dataset.f;
            blRender();
        }));

        document.querySelectorAll('#bl-cats .pill-cat input').forEach(r => r.addEventListener('change', () => {
            document.querySelectorAll('#bl-cats .pill-cat').forEach(p => p.classList.remove('on'));
            r.closest('.pill-cat').classList.add('on');
        }));

        document.addEventListener('DOMContentLoaded', () => {
            startPolling(blStateUrl, (data) => { blItems = data.items; blRender(); }, { interval: 1600 });
        });
    </script>
@endpush
