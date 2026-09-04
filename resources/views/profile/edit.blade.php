@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="fadeIn">

        {{-- Carte identité + couple --}}
        <div class="card center pad-lg">
            @if ($user->hasPhoto())
                <div id="avatar-big" class="avatar avatar-lg" style="margin:0 auto; background:{{ $user->avatarColor() }}; overflow:hidden">
                    <img src="{{ $user->photoUrl() }}" alt="Photo de profil" style="width:100%; height:100%; object-fit:cover">
                </div>
            @else
                <div id="avatar-big" class="avatar avatar-lg" style="margin:0 auto; background:{{ $user->avatarColor() }}">
                    {{ $user->avatarInitial() }}
                </div>
            @endif
            <h1 class="title">{{ $user->name }} <span class="muted" style="font-weight:500">· {{ $user->gender ?? '·' }}</span></h1>

            <div class="row gap8 items-center" style="justify-content:center; border:none; padding:0">
                <label class="btn btn-sm btn-soft">
                    📷 Photo de profil
                    <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp" style="display:none">
                </label>
                @if ($user->hasPhoto())
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
            <div class="row gap8 wrap" style="display:flex;flex-wrap:wrap;gap:8px">
                <a href="{{ route('info.show', 'modes-de-jeu') }}" class="btn btn-sm btn-soft">🎮 Modes de jeu</a>
                <a href="{{ route('info.show', 'categories-questions') }}" class="btn btn-sm btn-soft">🗂️ Catégories de questions</a>
                <a href="{{ route('info.show', 'installation') }}" class="btn btn-sm btn-soft">📲 Installer l'app</a>
                <a href="{{ route('info.show', 'a-propos') }}" class="btn btn-sm btn-soft">💞 À propos</a>
                <a href="{{ route('info.show', 'contact') }}" class="btn btn-sm btn-soft">💬 Contact & support</a>
            </div>
            <div class="divider"></div>
            <div class="row gap8 wrap" style="display:flex;flex-wrap:wrap;gap:8px">
                <a href="{{ route('info.show', 'confidentialite') }}" class="btn btn-sm btn-ghost">🔒 Confidentialité</a>
                <a href="{{ route('info.show', 'cgu') }}" class="btn btn-sm btn-ghost">📜 Conditions d'utilisation</a>
                <a href="{{ route('info.show', 'mentions-legales') }}" class="btn btn-sm btn-ghost">⚖️ Mentions légales</a>
                <a href="{{ route('info.show', 'cookies') }}" class="btn btn-sm btn-ghost">🍪 Cookies</a>
                <a href="{{ route('info.show', 'securite') }}" class="btn btn-sm btn-ghost">🛡️ Sécurité</a>
            </div>
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
        const photoInput = document.getElementById('photo-input');
        if (photoInput) {
            photoInput.addEventListener('change', async () => {
                if (!photoInput.files.length) return;
                const file = photoInput.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    toast('Image trop lourde (max 2 Mo).', 'error');
                    photoInput.value = '';
                    return;
                }
                const fd = new FormData();
                fd.append('photo', file);
                try {
                    const res = await fetch('{{ route('profile.photo') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: fd,
                    });
                    if (res.ok) {
                        toast('Photo mise à jour !', 'success');
                        location.reload();
                    } else {
                        const data = await res.json().catch(() => ({}));
                        toast(data.message || 'Échec de l\'upload.', 'error');
                    }
                } catch (e) {
                    toast('Erreur réseau.', 'error');
                }
            });
        }

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