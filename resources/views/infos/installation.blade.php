@extends('layouts.public')

@section('title', "Installer l'application — Double Jeu")

@section('content')
    <section class="hero center">
        <h1 class="title" style="font-size:28px">📲 Installer l'application</h1>
        <p class="muted" style="max-width:460px;margin:0 auto">Double Jeu fonctionne comme une application (PWA) : installation gratuite, accès direct et mode hors ligne.</p>
    </section>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:20px">
        <h2 class="section-title">Instructions d'installation</h2>

        <h3 style="margin-top:16px">📱 Android (Chrome)</h3>
        <p>Ouvrez le site dans Chrome, puis :</p>
        <ul>
            <li>Touchez le menu <strong>⋮</strong> (en haut à droite) ;</li>
            <li>Touchez <strong>« Ajouter à l'écran d'accueil »</strong> ;</li>
            <li>Confirmez avec <strong>« Ajouter »</strong>.</li>
        </ul>

        <h3 style="margin-top:16px">🍏 iPhone & iPad (Safari)</h3>
        <ul>
            <li>Touchez le bouton <strong>Partager</strong> ;</li>
            <li>Touchez <strong>« Sur l'écran d'accueil »</strong> ;</li>
            <li>Confirmez avec <strong>« Ajouter »</strong>.</li>
        </ul>

        <h3 style="margin-top:16px">💻 Ordinateur (Chrome, Edge, Firefox)</h3>
        <ul>
            <li>Cliquez sur l'icône <strong>d'installation</strong> dans la barre d'adresse, ou</li>
            <li>Menu <strong>⋮</strong> → <strong>« Installer Double Jeu »</strong>, ou</li>
            <li>Utilisez le bouton <strong>« Installer »</strong> qui apparaît automatiquement.</li>
        </ul>
    </div>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">Compatibilité des appareils</h2>
        <p>Double Jeu fonctionne sur tous les navigateurs modernes : Chrome, Edge, Safari, Firefox, sur Android, iOS et ordinateur.</p>
    </div>

    <div class="card pad-lg" style="max-width:680px;margin:0 auto;margin-top:16px">
        <h2 class="section-title">Fonctionnement hors ligne</h2>
        <p>Une fois installée, l'application utilise un Service Worker : les pages et ressources déjà visitées restent accessibles hors connexion. Les contenus échangés par votre couple se synchroniseront automatiquement au retour du réseau.</p>
    </div>
@endsection