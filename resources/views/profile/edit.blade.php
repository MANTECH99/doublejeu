@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="fadeIn">

        {{-- Carte identité + couple --}}
        <div class="card center pad-lg">
            @if ($user->hasPendingPhoto())
                {{-- Aperçu de la photo en attente : la photo actuelle n'est remplacée qu'après validation. --}}
                <div id="avatar-big" class="avatar avatar-lg" style="margin:0 auto; background:{{ $user->avatarColor() }}; overflow:hidden">
                    <img src="{{ $user->pendingPhotoUrl() }}" alt="Nouvelle photo de profil" style="width:100%; height:100%; object-fit:cover">
                </div>
                <p class="tiny muted" style="margin:8px 0 0">Nouvelle photo — pas encore appliquée</p>
            @elseif ($user->hasPhoto())
                <div id="avatar-big" class="avatar avatar-lg" style="margin:0 auto; background:{{ $user->avatarColor() }}; overflow:hidden">
                    <img src="{{ $user->photoUrl() }}" alt="Photo de profil" style="width:100%; height:100%; object-fit:cover">
                </div>
            @else
                <div id="avatar-big" class="avatar avatar-lg" style="margin:0 auto; background:{{ $user->avatarColor() }}">
                    {{ $user->avatarInitial() }}
                </div>
            @endif
            <h1 class="title">{{ $user->name }} <span class="muted" style="font-weight:500">· {{ $user->gender ?? '·' }}</span></h1>

            <div id="photo-progress" class="photo-progress" hidden>
                <div class="photo-progress-bar"><span id="photo-progress-fill"></span></div>
                <span class="tiny muted" id="photo-progress-label">Chargement…</span>
            </div>

            <div class="row gap8 items-center" style="justify-content:center; border:none; padding:0">
                <label class="btn btn-sm btn-soft">
                    📷 Photo de profil
                    <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp" style="display:none">
                </label>
                @if ($user->hasPendingPhoto())
                    <form method="POST" action="{{ route('profile.photo.apply') }}">
                        @csrf
                        <button class="btn btn-sm btn-primary">Valider</button>
                    </form>
                    <form method="POST" action="{{ route('profile.photo.cancel') }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost">Annuler</button>
                    </form>
                @elseif ($user->hasPhoto())
                    <form method="POST" action="{{ route('profile.photo.delete') }}" onsubmit="return confirm('Supprimer ta photo de profil ?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-ghost">Supprimer</button>
                    </form>
                @endif
            </div>

            @if ($user->coupleModel)
                <div class="chip" style="margin-top:6px">
                    💞 avec <strong>{{ $user->partner?->name ?? 'ton/ta partenaire' }}</strong>
                </div>
                <div class="muted mt8">Code couple :</div>
                <div id="couple-code" class="code" style="user-select:all">{{ $user->coupleModel->code_unique }}</div>
                <button class="btn btn-sm btn-soft mt8" onclick="copyCode()">Copier le code</button>
                <a href="{{ route('couple.setup') }}" class="btn btn-sm btn-ghost mt8 btn-block">⚙️ Configurer mon couple</a>
                <form method="POST" action="{{ route('couple.leave') }}" onsubmit="return confirm('Quitter ce couple ? Ton profil sera délié.')">
                    @csrf
                    <button class="btn btn-sm btn-danger-outline mt8 btn-block">Quitter le couple</button>
                </form>
            @else
                <p class="muted">Aucun couple lié pour l'instant.</p>
                <a href="{{ route('couple.setup') }}" class="btn btn-sm btn-primary mt8">Créer / rejoindre un couple</a>
            @endif
        </div>

        {{-- Notifications push --}}
        <section class="card pad-lg">
            <h2 class="section-title">🔔 Notifications</h2>
            <p class="muted" style="font-size:13px">Reçois une notification quand ton/ta partenaire joue ou gagne des points.</p>
            <div class="row gap8 mt8">
                <button id="btn-push-enable" class="btn btn-sm btn-primary">Activer les notifications</button>
                <button id="btn-push-test" class="btn btn-sm btn-ghost">Envoyer un test</button>
            </div>
        </section>

        {{-- Connexion biométrique (Face ID / empreinte) --}}
        <section class="card pad-lg">
            <h2 class="section-title">🫣 Connexion avec Face ID</h2>
            <p class="muted" style="font-size:13px">Une fois enregistré, tu peux te connecter d'un seul scan, sans email ni mot de passe.</p>
            <div class="row gap8 mt8">
                <button id="btn-biometric-add" class="btn btn-sm btn-primary">🗝️ Enregistrer cet appareil</button>
            </div>
            @if ($user->webauthnKeys->isNotEmpty())
                <div class="divider"></div>
                @foreach ($user->webauthnKeys as $key)
                    <div class="row">
                        <div class="grow">
                            <strong style="font-size:14px">🗝️ {{ $key->name }}</strong>
                            <div class="tiny muted">{{ $key->created_at->diffForHumans() }}</div>
                        </div>
                        <form method="POST" action="{{ route('webauthn.destroy', $key) }}" onsubmit="return confirm('Retirer cet appareil ?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-ghost">Retirer</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </section>

        {{-- Code PIN de déverrouillage --}}
        <section class="card pad-lg" id="pin">
            <h2 class="section-title">🔢 Code PIN</h2>

            @if ($user->hasPin())
                <p class="muted" style="font-size:13px">
                    Un code à 6 chiffres remplace l'email à l'ouverture de l'app, mais seulement
                    sur les appareils listés ci-dessous.
                </p>
            @else
                <p class="muted" style="font-size:13px">
                    Choisis un code à 6 chiffres pour ouvrir l'app d'un simple tap, sur cet
                    appareil. Ton mot de passe reste nécessaire pour le poser.
                </p>
            @endif

            <form method="POST" action="{{ route('pin.update') }}">
                @csrf
                @method('PUT')

                <label class="label">Mot de passe actuel</label>
                <input class="input" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password', 'updatePin')<p class="err">{{ $message }}</p>@enderror

                <label class="label mt8">
                    {{ $user->hasPin() ? 'Nouveau code PIN' : 'Code PIN' }}
                </label>
                <input class="input" type="text" name="pin" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required placeholder="6 chiffres" autocomplete="off"
                       style="letter-spacing:8px; font-size:20px; text-align:center">
                @error('pin', 'updatePin')<p class="err">{{ $message }}</p>@enderror

                <label class="label mt8">Confirmer le code</label>
                <input class="input" type="text" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required placeholder="••••••" autocomplete="off"
                       style="letter-spacing:8px; font-size:20px; text-align:center">
                @error('pin_confirmation', 'updatePin')<p class="err">{{ $message }}</p>@enderror

                <label class="label mt8">Nom de cet appareil</label>
                <input class="input" type="text" name="appareil" id="pin-appareil"
                       value="{{ old('appareil') }}" maxlength="60" placeholder="Cet appareil">
                @error('appareil', 'updatePin')<p class="err">{{ $message }}</p>@enderror

                <button class="btn btn-soft btn-block mt16">
                    {{ $user->hasPin() ? 'Modifier le code PIN' : 'Activer le code PIN' }}
                </button>
            </form>

            @if ($user->hasPin())
                @if ($user->deviceTokens->isNotEmpty())
                    <div class="divider"></div>
                    <h3 style="font-size:14px; margin:0 0 4px">Appareils de confiance</h3>
                    <p class="tiny muted" style="margin:0 0 10px">
                        Seul un appareil listé ici peut se déverrouiller avec le code PIN.
                    </p>
                    @foreach ($user->deviceTokens as $device)
                        <div class="row">
                            <div class="grow">
                                <strong style="font-size:14px">📱 {{ $device->name }}</strong>
                                <div class="tiny muted">
                                    @if ($device->last_used_at)
                                        Utilisé {{ $device->last_used_at->diffForHumans() }}
                                    @else
                                        Jamais utilisé
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('device.destroy', $device) }}"
                                  onsubmit="return confirm('Retirer cet appareil ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-ghost">Retirer</button>
                            </form>
                        </div>
                    @endforeach
                    <button type="button" id="btn-pin-ajouter" class="btn btn-sm btn-soft btn-block mt8">
                        📱 Faire confiance à cet appareil
                    </button>
                @endif

                <div class="divider"></div>
                <form method="POST" action="{{ route('pin.destroy') }}"
                      onsubmit="return confirm('Supprimer le code PIN ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger-outline btn-block">Supprimer le code PIN</button>
                </form>
            @endif
        </section>

        {{-- Informations du profil --}}
        <section class="card pad-lg">
            <h2 class="section-title">✏️ Mes informations</h2>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')
                <label class="label">Prénom</label>
                <input class="input" type="text" name="name" value="{{ old('name', $user->name) }}" required>

                <label class="label mt8">Sexe / genre</label>
                <input class="input" type="text" name="gender" list="gender-options" value="{{ old('gender', $user->gender) }}">
                <datalist id="gender-options">
                    <option value="Femme"></option>
                    <option value="Homme"></option>
                    <option value="Neutre"></option>
                    <option value="Autre"></option>
                </datalist>

                <label class="label mt8">Date de naissance (anniversaire 🎂)</label>
                <input class="input" type="date" name="date_naissance" value="{{ old('date_naissance', $user->date_naissance?->format('Y-m-d')) }}">

                <label class="label mt8">Email</label>
                <input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required>

                <button class="btn btn-primary btn-block mt16">Enregistrer</button>
            </form>
        </section>

        {{-- Mot de passe --}}
        <section class="card pad-lg">
            <h2 class="section-title">🔒 Mot de passe</h2>
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')
                <label class="label">Mot de passe actuel</label>
                <input class="input" type="password" name="current_password" required>
                @error('current_password', 'updatePassword')<p class="err">{{ $message }}</p>@enderror

                <label class="label mt8">Nouveau mot de passe</label>
                <input class="input" type="password" name="password" required>
                @error('password', 'updatePassword')<p class="err">{{ $message }}</p>@enderror

                <label class="label mt8">Confirmer</label>
                <input class="input" type="password" name="password_confirmation" required>

                <button class="btn btn-soft btn-block mt16">Mettre à jour</button>
            </form>
        </section>

        {{-- Infos & légal --}}
        <section class="card pad-lg">
            <h2 class="section-title">ℹ️ Infos & légal</h2>
            <div>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'modes-de-jeu') }}"><span>🎮 Modes de jeu</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'categories-questions') }}"><span>🗂️ Catégories de questions</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'installation') }}"><span>📲 Installer l'app</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'a-propos') }}"><span>💞 À propos</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'contact') }}"><span>💬 Contact & support</span><span class="info-arrow">></span></a>
            </div>
            <div class="divider"></div>
            <div>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'confidentialite') }}"><span>🔒 Confidentialité</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'cgu') }}"><span>📜 Conditions d'utilisation</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'mentions-legales') }}"><span>⚖️ Mentions légales</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link" href="{{ route('info.show', 'cookies') }}"><span>🍪 Cookies</span><span class="info-arrow">></span></a>
                <a class="flex between items-center info-link info-link-last" href="{{ route('info.show', 'securite') }}"><span>🛡️ Sécurité</span><span class="info-arrow">></span></a>
            </div>
        </section>

        {{-- Apparence --}}
        <section class="card pad-lg">
            <h2 class="section-title">🎨 Apparence</h2>
            {{-- Le libellé et l'icône sont réécrits par applyTheme() : le bouton
                 fait défiler Rose → Sombre → Blanc. Ce markup n'est visible
                 qu'avant l'exécution d'app.js ; il doit donc annoncer le
                 thème par défaut, sinon l'utilisateur voit « Sombre » le temps
                 d'un aller-retour réseau sur un site rose. --}}
            <button type="button" class="flex between items-center info-link info-link-last" data-theme-toggle style="background:none;border:none;cursor:pointer;width:100%;text-align:left">
                <span><span class="theme-ico">🌹</span> Thème : <span class="theme-label">Rose</span></span>
                <span class="info-arrow">></span>
            </button>
        </section>

        {{-- Déconnexion --}}
        <section class="card pad-lg center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost btn-block">🚪 Déconnecter</button>
            </form>
        </section>

        {{-- Supprimer le compte --}}
        <section class="card pad-lg" style="border-color:var(--danger,#ff6b6b)">
            <h2 class="section-title">🗑️ Supprimer le compte</h2>
            <p class="muted" style="font-size:13px">Cette action est irréversible et efface toutes tes données.</p>
            <details class="mt16">
                <summary class="btn btn-sm btn-danger-outline" style="display:inline-block">Supprimer mon compte</summary>
                <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Confirmer la suppression définitive ?')" class="mt16">
                    @csrf
                    @method('DELETE')
                    <label class="label">Mot de passe pour confirmer</label>
                    <input class="input" type="password" name="password" required>
                    @error('password', 'userDeletion')<p class="err">{{ $message }}</p>@enderror
                    <button class="btn btn-danger btn-block mt8">Supprimer définitivement</button>
                </form>
            </details>
        </section>

    </div>
@endsection

@push('scripts')
<script>
    function copyCode() {
        const code = document.getElementById('couple-code');
        navigator.clipboard?.writeText(code.textContent.trim())
            .then(() => toast('Code copié !', 'success'))
            .catch(() => toast('Impossible de copier.', 'error'));
    }

    document.addEventListener('DOMContentLoaded', () => {
        const biometricBtn = document.getElementById('btn-biometric-add');

        if (biometricBtn && !window.PublicKeyCredential) {
            biometricBtn.closest('section')?.remove();
        }

        if (biometricBtn) {
            const b64urlToBuf = (b) => {
                let s = String(b).replace(/-/g, '+').replace(/_/g, '/');
                while (s.length % 4) s += '=';
                const bin = atob(s);
                const arr = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                return arr;
            };
            const bufToB64 = (a) => btoa(String.fromCharCode.apply(null, new Uint8Array(a)));

            biometricBtn.addEventListener('click', async () => {
                biometricBtn.disabled = true;
                biometricBtn.textContent = 'Scan en cours…';
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                try {
                    const res = await fetch('{{ route('webauthn.store.options') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('Impossible de préparer l\'enregistrement.');
                    const data = await res.json();
                    const options = data.publicKey;
                    options.challenge = b64urlToBuf(options.challenge);
                    options.user.id = b64urlToBuf(options.user.id);
                    if (options.excludeCredentials) {
                        options.excludeCredentials = options.excludeCredentials.map((c) => ({
                            id: b64urlToBuf(c.id), type: c.type, transports: c.transports,
                        }));
                    }
                    const cred = await navigator.credentials.create({ publicKey: options });
                    const payload = {
                        id: cred.id,
                        type: cred.type,
                        rawId: bufToB64(cred.rawId),
                        name: navigator.platform || 'Mon appareil',
                        response: {
                            clientDataJSON: bufToB64(cred.response.clientDataJSON).replace(/=+$/, ''),
                            attestationObject: bufToB64(cred.response.attestationObject),
                        },
                    };
                    const store = await fetch('{{ route('webauthn.store') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(payload),
                    });
                    if (!store.ok) throw new Error('L\'appareil n\'a pas été accepté.');
                    toast('Appareil biométrique enregistré !', 'success');
                    setTimeout(() => location.reload(), 700);
                } catch (e) {
                    const message = /NotAllowedError|not allowed|cancelled/i.test(String(e))
                        ? 'Enregistrement annulé.'
                        : (e && e.message ? e.message : 'Échec de l\'enregistrement.');
                    toast(message, 'error');
                    biometricBtn.disabled = false;
                    biometricBtn.textContent = '🗝️ Enregistrer cet appareil';
                }
            });
        }

        const photoInput = document.getElementById('photo-input');
        const photoBarre = document.getElementById('photo-progress');
        const photoRemplissage = document.getElementById('photo-progress-fill');
        const photoLabel = document.getElementById('photo-progress-label');

        if (photoInput) {
            photoInput.addEventListener('change', function () {
                if (!photoInput.files.length) return;
                const file = photoInput.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    toast('Image trop lourde (max 2 Mo).', 'error');
                    photoInput.value = '';
                    return;
                }

                /* Aperçu immédiat : l'utilisateur voit la photo avant la fin du transfert. */
                const apercu = document.getElementById('avatar-big');
                const htmlInitial = apercu ? apercu.innerHTML : '';
                const url = URL.createObjectURL(file);
                if (apercu) {
                    apercu.innerHTML = '<img src="' + url + '" alt="Aperçu" style="width:100%; height:100%; object-fit:cover">';
                }

                const ko = (message) => {
                    photoInput.disabled = false;
                    photoInput.value = '';
                    if (photoBarre) photoBarre.hidden = true;
                    if (apercu) apercu.innerHTML = htmlInitial;
                    URL.revokeObjectURL(url);
                    toast(message, 'error');
                };

                const fd = new FormData();
                fd.append('photo', file);

                photoInput.disabled = true;
                if (photoBarre) photoBarre.hidden = false;
                if (photoRemplissage) photoRemplissage.style.width = '0%';
                if (photoLabel) photoLabel.textContent = 'Chargement… 0 %';

                /* XHR et non fetch : seul XHR expose la progression d'envoi. */
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '{{ route('profile.photo') }}');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) return;
                    const pct = Math.round((e.loaded / e.total) * 100);
                    if (photoRemplissage) photoRemplissage.style.width = pct + '%';
                    if (photoLabel) photoLabel.textContent = pct < 100 ? 'Chargement… ' + pct + ' %' : 'Traitement…';
                });

                xhr.addEventListener('load', function () {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        if (photoRemplissage) photoRemplissage.style.width = '100%';
                        if (photoLabel) photoLabel.textContent = 'Photo chargée, mets à jour.';
                        toast('Photo chargée, vérifie-la puis mets à jour.', 'success');
                        setTimeout(() => location.reload(), 900);
                        return;
                    }
                    let message = 'Échec de l\'upload.';
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.errors && data.errors.photo) message = data.errors.photo[0];
                    } catch (e) {}
                    ko(message);
                });

                xhr.addEventListener('error', () => ko('Erreur réseau.'));
                xhr.send(fd);
            });
        }

        const pinAppareil = document.getElementById('pin-appareil');
        if (pinAppareil && !pinAppareil.value) {
            pinAppareil.placeholder = navigator.platform || 'Cet appareil';
        }

        const pinAjouter = document.getElementById('btn-pin-ajouter');
        if (pinAjouter) {
            pinAjouter.addEventListener('click', async function () {
                const nom = pinAppareil?.value.trim() || navigator.platform || 'Cet appareil';
                pinAjouter.disabled = true;
                const { ok, data } = await api('{{ route('device.store') }}', {
                    method: 'POST',
                    body: { name: nom },
                });
                if (ok && data && data.token) {
                    window.djAppareil?.ecrire(data.token, data.name);
                    toast('Cet appareil peut maintenant se déverrouiller avec le code PIN.', 'success');
                    setTimeout(() => location.reload(), 800);
                    return;
                }
                pinAjouter.disabled = false;
            });
        }

        @php
            /* Le jeton posé par la requête est consommé ici : il ne doit pas survivre au rendu. */
            $pinJeton = session('pin');
            session()->forget('pin');
        @endphp

        @if ($pinJeton)
            window.djAppareil?.ecrire(@json($pinJeton['token']), @json($pinJeton['nom']));
        @endif

        const enableBtn = document.getElementById('btn-push-enable');
        const testBtn = document.getElementById('btn-push-test');

        const refresh = () => {
            if (enableBtn) {
                enableBtn.textContent = window.notifications?.subscribed ? 'Désactiver les notifications' : 'Activer les notifications';
            }
        };
        if (enableBtn) {
            enableBtn.addEventListener('click', async () => {
                if (window.notifications?.subscribed) {
                    const reg = await navigator.serviceWorker?.ready;
                    const sub = await reg.pushManager.getSubscription();
                    if (sub) {
                        await api('/notifications/unsubscribe', { method: 'POST', body: { endpoint: sub.endpoint } });
                        await sub.unsubscribe();
                    }
                    window.notifications.subscribed = false;
                    localStorage.removeItem('dj_push');
                    toast('Notifications désactivées.', 'info');
                    refresh();
                } else {
                    const ok = await window.notifications?.subscribe();
                    if (ok) await api('/notifications/test', { method: 'POST' });
                }
            });
        }
        if (testBtn) {
            testBtn.addEventListener('click', async () => {
                const res = await api('/notifications/test', { method: 'POST' });
                if (res.ok) toast(res.data.message, res.data.sent > 0 ? 'success' : 'info');
            });
        }

        refresh();
        window.notifications?.init().then(refresh);
    });
</script>
@endpush