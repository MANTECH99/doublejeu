@extends('layouts.public')

@section('title', 'Sécurité des données — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">🛡️ Sécurité des données</h1>
        <p class="muted" style="max-width:480px;margin:0 auto">Comment vos conversations et vos données sont protégées.</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">

        <h2 class="section-title">Chiffrement des données</h2>
        <p>
            Vos échanges sont chiffrés en transit via HTTPS (TLS) entre votre appareil et nos serveurs.
            Les mots de passe sont stockés avec un algorithme de hachage à sens unique (bcrypt) : personne,
            y compris nous, ne peut lire votre mot de passe.
        </p>

        <h2 class="section-title">Confidentialité des conversations</h2>
        <ul>
            <li>Vos messages et contenus ne sont visibles que par les deux membres du couple lié.</li>
            <li>Les notifications push sont minimales : elles ne révèlent pas le contenu du message.</li>
            <li>Vous pouvez supprimer un message pour tout le monde à tout moment.</li>
            <li>La suppression du compte efface définitivement les données du couple.</li>
        </ul>

        <h2 class="section-title">Bonnes pratiques recommandées</h2>
        <ul>
            <li>Utilisez un mot de passe unique et robuste pour votre compte.</li>
            <li>Activez la vérification de l'e-mail à l'inscription.</li>
            <li>Ne partagez jamais votre code de liaison de couple publiquement.</li>
        </ul>

        <p class="muted" style="font-size:12px;margin-top:24px">Un problème de sécurité ? Signalez-le via la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>
    </div>
@endsection