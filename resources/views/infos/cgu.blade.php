@extends('layouts.public')

@section('title', "Conditions d'utilisation — Double Jeu")

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">📜 Conditions d'utilisation</h1>
        <p class="muted" style="max-width:480px;margin:0 auto">Dernière mise à jour : septembre 2026</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">

        <h2 class="section-title">1. Acceptation des conditions</h2>
        <p>En créant un compte, vous acceptez pleinement les présentes conditions. Si vous ne les acceptez pas, veuillez ne pas utiliser le service.</p>

        <h2 class="section-title">2. Règles d'utilisation du jeu</h2>
        <ul>
            <li>Le service est réservé aux couples : un compte doit être lié à un autre compte via un code de liaison.</li>
            <li>Vous vous engagez à utiliser l'application de manière respectueuse et à ne pas nuire aux autres utilisateurs.</li>
            <li>Les contenus échangés entre partenaires restent privés au sein du couple.</li>
        </ul>

        <h2 class="section-title">3. Public & âge minimum</h2>
        <p>L'application est destinée aux personnes majeures. Certaines catégories de questions (intimité, fantasmes) abordent des sujets réservés à un public adulte. En utilisant le service, vous confirmez avoir au moins 18 ans.</p>

        <h2 class="section-title">4. Propriété intellectuelle</h2>
        <p>L'application, son code, ses textes et ses éléments graphiques sont la propriété de l'éditeur ou de leurs auteurs respectifs. Toute reproduction ou utilisation non autorisée est interdite.</p>

        <h2 class="section-title">5. Limitations de responsabilité</h2>
        <p>Le service est fourni « tel quel ». L'éditeur ne saurait être tenu responsable des interruptions, des pertes de données ou des conséquences de l'usage des jeux entre partenaires.</p>

        <h2 class="section-title">6. Résiliation du compte</h2>
        <p>Vous pouvez supprimer votre compte à tout moment depuis la page <a href="{{ route('profile.edit') }}">Profil</a>. L'éditeur se réserve le droit de résilier un compte en cas de non-respect des présentes conditions.</p>

        <p class="muted" style="font-size:12px;margin-top:24px">Pour toute question sur ces conditions, écrivez-nous via la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>
    </div>
@endsection