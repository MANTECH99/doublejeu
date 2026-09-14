<x-guest-layout>

    <div class="card center" style="padding:28px 22px">
        <div style="font-size:52px; margin-bottom:4px">💞</div>
        <h1 class="title" style="font-size:20px">Double Jeu</h1>
        <p class="muted" style="margin-bottom:22px">Le jeu de couple</p>

        <button id="btn-webauthn" type="button" class="btn btn-primary btn-block" style="font-size:15px">
            🫣 Se connecter avec Face ID
        </button>
        <p class="tiny muted center" style="margin:8px 0 0; line-height:1.4">
            Reconnaissance faciale, empreinte digitale<br>ou Windows Hello, sans rien taper.
        </p>
        <p id="webauthn-err" class="err center mt8" style="display:none; margin:12px 0 0"></p>

        <div class="guest-divider">
            <span>ou utilise ton email</span>
        </div>

        <form method="POST" action="{{ route('login') }}" style="text-align:left">
            @csrf

            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username webauthn"
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

            <button class="btn btn-ghost btn-block mt16" type="submit" style="font-size:14px">
                Se connecter avec un mot de passe
            </button>
        </form>

        <div class="guest-divider">
            <span>ou</span>
        </div>

        <a href="{{ route('register') }}" class="btn btn-ghost btn-block" style="font-size:14px">
            Créer un compte
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('btn-webauthn');
            var errEl = document.getElementById('webauthn-err');
            var conditionalCtrl = null;
            var busy = false;

            function b64urlToBuf(b) {
                var s = String(b).replace(/-/g, '+').replace(/_/g, '/');
                while (s.length % 4) s += '=';
                var bin = atob(s), arr = new Uint8Array(bin.length);
                for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                return arr;
            }
            function bufToB64(a) {
                return btoa(String.fromCharCode.apply(null, new Uint8Array(a)));
            }

            function showErr(msg) {
                errEl.textContent = msg;
                errEl.style.display = 'block';
            }

            function adaptOptions(options) {
                options.challenge = b64urlToBuf(options.challenge);
                if (options.allowCredentials && options.allowCredentials.length) {
                    options.allowCredentials = options.allowCredentials.map(function (c) {
                        return { id: b64urlToBuf(c.id), type: c.type, transports: c.transports };
                    });
                }
                return options;
            }

            function buildPayload(cred) {
                return {
                    id: cred.id,
                    type: cred.type,
                    rawId: bufToB64(cred.rawId),
                    response: {
                        authenticatorData: bufToB64(cred.response.authenticatorData).replace(/=+$/, ''),
                        clientDataJSON: bufToB64(cred.response.clientDataJSON).replace(/=+$/, ''),
                        signature: bufToB64(cred.response.signature),
                        userHandle: cred.response.userHandle ? bufToB64(cred.response.userHandle) : null,
                    },
                };
            }

            async function requestOptions() {
                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var res = await fetch('{{ route('webauthn.auth.options') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
                var data = await res.json().catch(function () { return {}; });
                if (!res.ok) throw new Error('Impossible de préparer le scan.');
                return data.publicKey;
            }

            async function submitAssertion(payload) {
                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var login = await fetch('{{ route('webauthn.auth') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload)
                });
                var rep = await login.json().catch(function () { return {}; });
                if (!login.ok || !rep.result) throw new Error('Connexion biométrique refusée.');
                window.location.href = rep.callback || '/dashboard';
            }

            async function runAssertion(mediation, abortSignal) {
                if (busy) return;
                busy = true;
                var options = adaptOptions(await requestOptions());
                var cred = await navigator.credentials.get({
                    publicKey: options,
                    mediation: mediation,
                    signal: abortSignal,
                });
                await submitAssertion(buildPayload(cred));
            }

            if (!btn || !window.PublicKeyCredential) {
                btn.closest('.card').querySelector('.tiny').textContent = 'Face ID non disponible sur cet appareil.';
                btn.style.display = 'none';
                return;
            }

            function startConditional() {
                if (conditionalCtrl || !window.PublicKeyCredential) return;
                conditionalCtrl = new AbortController();
                runAssertion('conditional', conditionalCtrl.signal)
                    .catch(function (e) {
                        if (e && e.name === 'AbortError') return;
                        conditionalCtrl = null;
                    });
            }

            function startConditionalIfSupported() {
                if (!window.PublicKeyCredential || !window.PublicKeyCredential.isConditionalMediationAvailable) return;
                window.PublicKeyCredential.isConditionalMediationAvailable()
                    .then(function (available) {
                        if (available) startConditional();
                    })
                    .catch(function () {});
            }

            if (document.readyState === 'complete') {
                startConditionalIfSupported();
            } else {
                window.addEventListener('load', startConditionalIfSupported);
            }

            btn.addEventListener('click', async function () {
                errEl.style.display = 'none';
                if (conditionalCtrl) {
                    conditionalCtrl.abort();
                    conditionalCtrl = null;
                }
                busy = false;
                btn.disabled = true;
                btn.textContent = 'Scan en cours…';
                try {
                    await runAssertion('optional');
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    var message = e && e.message ? e.message : 'Échec du scan biométrique.';
                    if (/NotAllowedError|not allowed|PlatformAuthenticator/i.test(String(e))) {
                        message = 'Aucun appareil biométrique enregistré. Connecte-toi avec ton email puis ajoute un appareil dans le profil.';
                    }
                    showErr(message);
                    btn.disabled = false;
                    btn.textContent = '🫣 Se connecter avec Face ID';
                } finally {
                    busy = false;
                }
            });
        });
    </script>

</x-guest-layout>