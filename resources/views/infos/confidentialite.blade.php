@extends('layouts.public')

@section('title', 'Politique de confidentialité — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">🔒 Politique de confidentialité</h1>
        <p class="muted" style="max-width:480px;margin:0 auto">Dernière mise à jour : septembre 2026</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">

        <h2 class="section-title">1. Collecte des données personnelles</h2>
        <p>Double Jeu collecte uniquement les données nécessaires au fonctionnement du service :</p>
        <ul>
            <li><strong>Données de compte</strong> : prénom, adresse e-mail, mot de passe chiffré, date de naissance et genre (champ facultatif).</li>
            <li><strong>Données de jeu</strong> : scores, récompenses, historiques de parties, messages et réponses entre les membres d'un couple.</li>
            <li><strong>Données de profil</strong> : photo de profil (facultative) et code de liaison du couple.</li>
        </ul>

        <h2 class="section-title">2. Utilisation des informations</h2>
        <p>Vos données servent uniquement à :</p>
        <ul>
            <li>Créer et gérer votre compte ainsi que votre couple ;</li>
            <li>Faire fonctionner les jeux, la discussion et les récompenses ;</li>
            <li>Envoyer les notifications push que vous avez activées ;</li>
            <li>Améliorer le service et assurer sa sécurité.</li>
        </ul>
        <p>Nous ne vendons jamais vos données à des tiers.</p>

        <h2 class="section-title">3. Cookies et stockage local</h2>
        <p>Nous utilisons un cookie de session (obligatoire pour rester connecté·e) et le stockage local du navigateur pour les fonctionnalités hors ligne de l'application (PWA). Aucun cookie publicitaire n'est utilisé.</p>

        <h2 class="section-title">4. Partage avec des tiers</h2>
        <p>Vos données ne sont partagées avec aucun tiers à des fins commerciales. Le service s'appuie sur un hébergeur qui stocke les données de manière chiffrée. Les contenus que vous échangez entre partenaires ne sont visibles que par vous deux.</p>

        <h2 class="section-title">5. Droits des utilisateurs (RGPD)</h2>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez des droits suivants :</p>
        <ul>
            <li>Droit d'accès à vos données ;</li>
            <li>Droit de rectification ;</li>
            <li>Droit à l'effacement (« droit à l'oubli ») ;</li>
            <li>Droit à la portabilité ;</li>
            <li>Droit d'opposition et de limitation du traitement.</li>
        </ul>
        <p>Vous pouvez exercer ces droits depuis votre page <a href="{{ route('profile.edit') }}">Profil</a> ou en nous contactant via la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>

        <h2 class="section-title">6. Durée de conservation</h2>
        <p>Vos données sont conservées tant que votre compte est actif. La suppression de votre compte entraîne l'effacement définitif de l'ensemble de vos données personnelles, ainsi que de celles de votre couple.</p>

        <p class="muted" style="font-size:12px;margin-top:24px">En cas de litige, vous pouvez saisir la CNIL (https://www.cnil.fr).</p>
    </div>
@endsection