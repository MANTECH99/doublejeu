@extends('layouts.public')

@section('title', 'Catégories de questions — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">🗂️ Catégories de questions</h1>
        <p class="muted" style="max-width:460px;margin:0 auto">Les thématiques des questions et cartes disponibles dans les jeux.</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">
        <h2 class="section-title">❤️ Vie quotidienne</h2>
        <p>Les petites habitudes, les goûts, les souvenirs et les projets du quotidien qui font votre couple.</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">💬 Communication</h2>
        <p>Des questions pour mieux se connaître, s'écouter et dialoguer : comment vous êtes-vous rencontrés, ce que vous appréciez l'un chez l'autre…</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">❤️‍🔥 Intimité</h2>
        <p>
            Des sujets plus personnels et sensuels, réservés à un public adulte.
            Cette catégorie met la complicité à l'épreuve dans un cadre bienveillant.
        </p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">✨ Fantasmes</h2>
        <p>
            L'imagination pousse la porte : envies, rêves et scénarios à partager à deux.
            Contenu réservé aux adultes — les règles du respect mutuel restent toujours en vigueur.
        </p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">🗺️ Aventure</h2>
        <p>Sorties, voyages, défis et découvertes : de quoi nourrir vos envies d'explorer le monde à deux.</p>
    </section>

    <p class="muted center" style="font-size:13px;margin-top:18px">Les catégories intimité et fantasmes ne sont accessibles qu'aux personnes majeures. Voir les <a href="{{ route('info.show', 'cgu') }}">Conditions d'utilisation</a>.</p>
@endsection