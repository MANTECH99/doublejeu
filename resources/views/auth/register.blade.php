<x-guest-layout>

    <div class="card center" style="padding:28px 22px">
        <div style="font-size:52px; margin-bottom:4px">💞</div>
        <h1 class="title" style="font-size:20px">Crée ton compte</h1>
        <p class="muted" style="margin-bottom:22px">Rejoins Double Jeu et joue à deux</p>

        <form method="POST" action="{{ route('register') }}" style="text-align:left">
            @csrf

            <label class="label" for="name">Prénom</label>
            <input class="input" id="name" type="text" name="name"
                   value="{{ old('name') }}" required autofocus autocomplete="name"
                   placeholder="Ton prénom">

            <label class="label mt16" for="gender">Sexe / genre</label>
            <input class="input" id="gender" type="text" name="gender" list="gender-options"
                   value="{{ old('gender') }}" autocomplete="off"
                   placeholder="Femme, Homme, Autre…">
            <datalist id="gender-options">
                <option value="Femme"></option>
                <option value="Homme"></option>
                <option value="Neutre"></option>
                <option value="Autre"></option>
            </datalist>

            <label class="label mt16" for="email">Email</label>
            <input class="input" id="email" type="email" name="email"
                   value="{{ old('email') }}" required autocomplete="username"
                   placeholder="toi@exemple.com">

            <label class="label mt16" for="password">Mot de passe</label>
            <input class="input" id="password" type="password" name="password"
                   required autocomplete="new-password"
                   placeholder="Min. 8 caractères">

            <label class="label mt16" for="password_confirmation">Confirmer le mot de passe</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password"
                   placeholder="Retape ton mot de passe">

            <button class="btn btn-primary btn-block mt16" type="submit">
                Créer mon compte
            </button>
        </form>

        <div class="guest-divider">
            <span>ou</span>
        </div>

        <a href="{{ route('login') }}" class="btn btn-ghost btn-block" style="font-size:14px">
            J'ai déjà un compte
        </a>
    </div>

</x-guest-layout>
