@extends('layouts.public')

@section('title', 'Mentions légales — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">⚖️ Mentions légales</h1>
        <p class="muted" style="max-width:480px;margin:0 auto">Dernière mise à jour : septembre 2026</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">

        <h2 class="section-title">Identité de l'éditeur</h2>
        <p>
            Double Jeu est édité par :<br>
            <strong>[Raison sociale / Nom de l'éditeur]</strong><br>
            [Adresse du siège social]<br>
            Contact : voir la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>
        </p>

        <h2 class="section-title">Coordonnées de contact</h2>
        <p>Pour toute question, réclamation ou exercice de vos droits : via le formulaire de la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>

        <h2 class="section-title">Hébergeur du site</h2>
        <p>
            Le site est hébergé par :<br>
            <strong>[Nom de l'hébergeur]</strong> — [Adresse de l'hébergeur] — [Téléphone / site web]
        </p>

        <h2 class="section-title">Numéro d'enregistrement</h2>
        <p>Si l'éditeur est une société immatriculée : <strong>[N° SIRET / RCS]</strong>. (à compléter le cas échéant)</p>

        <p class="muted" style="font-size:12px;margin-top:24px">Conformément à la loi n°2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique (LCEN).</p>
    </div>
@endsection