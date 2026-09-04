@extends('layouts.public')

@section('title', 'Contact & support — Double Jeu')

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">💬 Contact & support</h1>
        <p class="muted" style="max-width:460px;margin:0 auto">Une question, un bug, un droit à exercer ? Écrivez-nous.</p>
        <div class="row gap8" style="justify-content:center;margin-top:18px">
            <a href="mailto:support@doublejeu.app" class="btn btn-primary">✉️ Envoyer un e-mail</a>
        </div>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">
        <h2 class="section-title">Comment nous contacter ?</h2>
        <p>Vous pouvez nous écrire à l'adresse <a href="mailto:support@doublejeu.app">support@doublejeu.app</a>. Nous répondons généralement sous 48 h ouvrées.</p>
        <p class="muted" style="font-size:13px">Pour exercer vos droits RGPD (accès, rectification, effacement, portabilité), précisez simplement votre e-mail de compte dans le message.</p>
    </div>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">FAQ — Questions fréquentes</h2>

        <h3 class="section-title" style="font-size:15px">🚀 Je n'arrive pas à lier mon couple</h3>
        <p>Vérifiez que vous et votre partenaire utilisez exactement le même code de liaison (sensible à la casse). Chaque compte ne peut être lié qu'à un seul couple.</p>

        <h3 class="section-title" style="font-size:15px">📲 Comment installer l'application ?</h3>
        <p>Tout est expliqué sur la page <a href="{{ route('info.show', 'installation') }}">Installer l'application</a>.</p>

        <h3 class="section-title" style="font-size:15px">🔔 Je ne reçois pas les notifications</h3>
        <p>Activez les notifications depuis votre page <a href="{{ route('profile.edit') }}">Profil</a> et vérifiez que votre navigateur les autorise.</p>

        <h3 class="section-title" style="font-size:15px">🗑️ Comment supprimer mon compte ?</h3>
        <p>Rendez-vous sur votre <a href="{{ route('profile.edit') }}">Profil</a>, rubrique « Supprimer le compte ». La suppression est définitive.</p>

        <h3 class="section-title" style="font-size:15px">🐛 Comment signaler un bug ?</h3>
        <p>Envoyez un e-mail avec une description du problème, l'appareil utilisé et le navigateur pour nous aider à le reproduire.</p>
    </div>
@endsection