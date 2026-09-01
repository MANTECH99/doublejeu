<x-guest-layout>

    <div class="card center" style="padding:28px 22px">
        <div style="font-size:52px; margin-bottom:4px">💞</div>
        <h1 class="title" style="font-size:20px">Double Jeu</h1>
        <p class="muted" style="margin-bottom:22px">Le jeu de couple</p>

        <form method="POST" action="{{ route('login') }}" style="text-align:left">
            @csrf

            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username"
                   placeholder="toi@exemple.com">

            <label class="label mt16" for="password">Mot de passe</label>
            <input class="input" id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••">

            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:16px">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-2); cursor:pointer">
                    <input type="checkbox" name="remember" style="accent-color:var(--primary); width:16px; height:16px">
                    Se souvenir
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" style="font-size:13px; color:var(--text-3); text-decoration:none">
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>

            <button class="btn btn-primary btn-block mt16" type="submit">
                Se connecter
            </button>
        </form>

        <div class="guest-divider">
            <span>ou</span>
        </div>

        <a href="{{ route('register') }}" class="btn btn-ghost btn-block" style="font-size:14px">
            Créer un compte
        </a>
    </div>

</x-guest-layout>
