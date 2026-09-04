@extends('layouts.public')

@section('title', 'Politique de cookies — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">🍪 Politique de cookies</h1>
        <p class="muted" style="max-width:480px;margin:0 auto">Dernière mise à jour : septembre 2026</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">

        <h2 class="section-title">1. Qu'est-ce qu'un cookie ?</h2>
        <p>Un cookie est un petit fichier déposé sur votre appareil lors de votre visite. Il permet à l'application de reconnaître votre session.</p>

        <h2 class="section-title">2. Cookies utilisés</h2>
        <ul>
            <li><strong>Cookie de session</strong> : strictement nécessaire pour vous maintenir connecté·e et sécuriser votre navigation.</li>
            <li><strong>CSRF token</strong> : protège les formulaires contre les attaques (obligatoire).</li>
            <li><strong>Stockage local (PWA)</strong> : permet le fonctionnement hors ligne ; ne quitte jamais votre appareil.</li>
        </ul>
        <p>Aucun cookie publicitaire ou de suivi tiers n'est déposé par Double Jeu.</p>

        <h2 class="section-title">3. Gérer vos cookies</h2>
        <p>Vous pouvez configurer votre navigateur pour bloquer ou supprimer les cookies. La désactivation du cookie de session empêchera la connexion à votre compte.</p>

        <h2 class="section-title">4. Consultation de la liste</h2>
        <p>La liste des cookies peut être consultée dans les outils de développement de votre navigateur (rubrique « Application » ou « Stockage »). Pour les navigateurs iOS/Android, consultez les réglages du navigateur.</p>

        <p class="muted" style="font-size:12px;margin-top:24px">Pour toute question, contactez-nous via la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>
    </div>
@endsection