@extends('layouts.public')

@section('title', 'Double Jeu — Des jeux pour deux')

@section('content')
    <section class="hero center">
        <div style="font-size:64px; margin-bottom:10px">💞</div>
        <h1 class="title" style="font-size:34px">Double Jeu</h1>
        <p class="subtitle" style="max-width:360px;margin:0 auto">
            Quatre jeux coquins et complices pour les couples à distance. À toi de jouer…
            et de faire gagner des points à votre amour.
        </p>
        <div class="row gap8" style="justify-content:center; margin-top:22px">
            <a href="{{ route('register') }}" class="btn btn-primary">Créer un compte</a>
            <a href="{{ route('login') }}" class="btn btn-ghost">Se connecter</a>
        </div>
    </section>

    <section class="game-grid" style="margin-top:28px">
        <div class="game-tile tile-vo">
            <div class="t-ico">🎭</div>
            <div class="t-name">Vérité ou Action</div>
            <div class="t-desc">Doux, chaud, brûlant… fais ton choix, réponds ou fonce.</div>
        </div>
        <div class="game-tile tile-ouinon">
            <div class="t-ico">⚖️</div>
            <div class="t-name">Oui ou Non</div>
            <div class="t-desc">10 questions pour se tester, et des missions à réaliser ensemble.</div>
        </div>
        <div class="game-tile tile-mission">
            <div class="t-ico">🕵️</div>
            <div class="t-name">Mission secrète</div>
            <div class="t-desc">Une mission discrète à accomplir… sans se faire démasquer.</div>
        </div>
        <div class="game-tile tile-enveloppe">
            <div class="t-ico">💌</div>
            <div class="t-name">Enveloppes</div>
            <div class="t-desc">Envoie une enveloppe, rouge, bleue ou verte, à ouvrir.</div>
        </div>
    </section>

    <section class="card center pad-lg" style="margin-top:28px">
        <h2 class="section-title">Comment ça marche ?</h2>
        <div class="steps">
            <div><span class="step-n">1</span><p>Inscrivez-vous chacun·e</p></div>
            <div><span class="step-n">2</span><p>Générez un code, liez votre couple</p></div>
            <div><span class="step-n">3</span><p>Jouez à distance, gagnez des points</p></div>
            <div><span class="step-n">4</span><p>Débloquez des récompenses 🎁</p></div>
        </div>
        <a href="{{ route('register') }}" class="btn btn-primary btn-block" style="margin-top:18px">Commencer maintenant</a>
    </section>
@endsection