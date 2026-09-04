@extends('layouts.public')

@section('title', 'À propos — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">💞 À propos</h1>
        <p class="muted" style="max-width:460px;margin:0 auto">L'histoire de Double Jeu, notre équipe et nos valeurs.</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">
        <h2 class="section-title">Histoire de l'application</h2>
        <p>
            Double Jeu est née d'une idée simple : passer une bonne soirée à deux, même à distance.
            Nous avons imaginé des jeux complices, coquins et bienveillants qui rapprochent les couples,
            qu'ils vivent sous le même toit ou à plusieurs centaines de kilomètres l'un de l'autre.
        </p>

        <h2 class="section-title">Valeurs du projet</h2>
        <ul>
            <li><strong>Complicité</strong> : des contenus conçus pour se reconnecter.</li>
            <li><strong>Bienveillance</strong> : un cadre respectueux, sans jugement.</li>
            <li><strong>Confidentialité</strong> : vos échanges restent privés.</li>
            <li><strong>Accessibilité</strong> : simple, rapide et utilisable hors ligne.</li>
        </ul>
    </div>

    <div class="card center pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">Équipe de développement</h2>
        <p class="muted">Double Jeu est développé avec soin par une petite équipe passionnée.</p>
        <div class="row gap8" style="justify-content:center;margin-top:14px">
            <span class="chip">👩‍💻 Intégration & front</span>
            <span class="chip">🧑‍💻 Backend & API</span>
            <span class="chip">🎨 Design & UX</span>
        </div>
        <p class="muted" style="font-size:13px;margin-top:16px">Une idée, une suggestion ? Écrivez-nous via la page <a href="{{ route('info.show', 'contact') }}">Contact & support</a>.</p>
    </div>
@endsection