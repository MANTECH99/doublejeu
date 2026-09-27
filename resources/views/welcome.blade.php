@extends('layouts.public')

@section('title', 'Double Jeu — Des jeux pour deux')

@push('head')
    {{-- L'accueil s'ouvre toujours en haut : le navigateur ne restaure pas la position précédente. --}}
    <script>
        if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
        window.scrollTo(0, 0);
        /* pageshow couvre aussi le retour arrière et la réouverture de la PWA. */
        window.addEventListener('pageshow', function () { window.scrollTo(0, 0); });
    </script>
@endpush

@section('content')
    <section class="hero center">
        <div style="font-size:64px; margin-bottom:10px">💞</div>
        <h1 class="title" style="font-size:34px">Double Jeu</h1>
        <p class="subtitle" style="max-width:360px;margin:0 auto">
            14 jeux coquins et complices pour les couples à distance. À toi de jouer…
            et de faire gagner des points à votre amour.
        </p>
        <div class="row gap8" style="justify-content:center; margin-top:22px">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Se déconnecter</button>
                </form>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Tableau de bord</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary">Créer un compte</a>
                <a href="{{ route('login') }}" class="btn btn-ghost">Se connecter</a>
            @endauth
        </div>
    </section>

    <section class="game-grid" style="margin-top:28px">
        <div class="game-tile tile-discussion">
            <div class="t-ico">💬</div>
            <div class="t-name">Discussion</div>
            <div class="t-desc">Messages, photos, vidéos et vocaux, rien que vous deux.</div>
        </div>
        <div class="game-tile tile-vo-accueil">
            <div class="t-ico">🎭</div>
            <div class="t-name">Vérité ou Action</div>
            <div class="t-desc">Doux, chaud, brûlant… fais ton choix, réponds ou fonce.</div>
        </div>
        <div class="game-tile tile-ludo">
            <div class="t-ico">🎲</div>
            <div class="t-name">Ludo à deux</div>
            <div class="t-desc">La course des pions, un lancer à la fois, jusqu'à la ligne.</div>
        </div>
        <div class="game-tile tile-quoridor">
            <div class="t-ico">🧱</div>
            <div class="t-name">Quoridor</div>
            <div class="t-desc">Barrez la route, avancez les pions : qui atteint l'autre ?</div>
        </div>
    </section>

    {{-- Présentation animée du projet --}}
    @php
        $jeux = [
            ['💬', 'Discussion', 'En privé, à deux', 'tile-discussion'],
            ['🎭', 'Vérité ou Action', 'Doux, chaud ou brûlant', 'tile-vo'],
            ['⚖️', 'Oui ou Non', '10 questions test', 'tile-ouinon'],
            ['🕵️', 'Mission secrète', 'Il/elle ne saura jamais', 'tile-mission'],
            ['💌', 'Enveloppes', 'Rouge, bleue, verte', 'tile-enveloppe'],
            ['❓', 'Tu me connais ?', 'Réponds à ma place', 'tile-quiz'],
            ['🙋', 'Qui de nous deux ?', 'Accord → +5 pts', 'tile-qui-nous-deux'],
            ['🌅', 'Question du jour', 'Une par jour, ensemble', 'tile-question'],
            ['🌦️', 'Météo du couple', 'Ton baromètre à deux', 'tile-meteo'],
            ['🧩', 'Mots croisés', 'Une grille, à deux', 'tile-mots-croises'],
            ['🧳', 'Bucket List', 'Vos projets, vos souvenirs', 'tile-bucket-list'],
            ['🗓️', 'Calendrier', 'Votre journée côte à côte', 'tile-calendrier'],
            ['🎲', 'Ludo à deux', 'La course des pions', 'tile-ludo'],
            ['🧱', 'Quoridor', 'Barre la route, gagne', 'tile-quoridor'],
        ];
        $nbJeux = count($jeux);
        /* Les data-duree des scènes : le total est calculé, il ne peut pas dériver. */
        $durees = ['lien' => 3400, 'discussion' => 4400, 'jeu' => 2500, 'journee' => 4200, 'points' => 4200, 'recompenses' => 4200, 'app' => 4600];
        $duree = $durees['lien'] + $durees['discussion'] + $nbJeux * $durees['jeu'] + $durees['journee'] + $durees['points'] + $durees['recompenses'] + $durees['app'];
        $nbScenes = $nbJeux + 6;
        $chrono = '0:00 / ' . intdiv($duree, 60000) . ':' . str_pad((string) intdiv($duree % 60000, 1000), 2, '0', STR_PAD_LEFT);
        $dureeLisible = intdiv($duree, 60000) > 0
            ? intdiv($duree, 60000) . ' minute' . (intdiv($duree, 60000) > 1 ? 's' : '')
            : intdiv($duree, 1000) . ' secondes';
    @endphp
    <section class="dj-pres" aria-labelledby="dj-pres-titre">
        <div class="dj-pres-head">
            <h2 class="section-title" id="dj-pres-titre">Le projet en {{ $nbScenes }} scènes</h2>
            <p>Le lien du couple, la discussion privée, les {{ $nbJeux }} jeux un par un, la journée type, les points, les récompenses et l'app à installer. Un clic, {{ $dureeLisible }}.</p>
        </div>

        <div class="dj-player" id="dj-player" data-state="idle">
            <div class="dj-stage" id="dj-stage">

                {{-- 1 — Le lien --}}
                <div class="dj-scene" data-scene="lien" data-duree="3400" data-legende="Le lien">
                    <div class="dj-duo">
                        <div class="dj-id dj-el dj-left">
                            <img class="a a1" src="{{ asset('images/alice.jpg') }}" alt="Alice" width="38" height="38">
                            <span class="n">Alice</span>
                        </div>
                        <div class="dj-mid dj-el dj-pop" style="--d:.3s">
                            <span class="dj-heart">💞</span>
                        </div>
                        <div class="dj-id dj-el dj-right" style="--d:.15s">
                            <img class="a a2" src="{{ asset('images/bob.jpg') }}" alt="Bob" width="38" height="38">
                            <span class="n">Bob</span>
                        </div>
                    </div>
                    <div class="dj-code dj-el dj-up" style="--d:.55s">7F2K-9Q</div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">Un code suffit</div>
                        <div class="dj-cap-d">Chacun s'inscrit, un code lie le couple. Vous jouez à distance, sur deux écrans, en même temps.</div>
                    </div>
                </div>

                {{-- 2 — La discussion privée --}}
                <div class="dj-scene" data-scene="discussion" data-duree="4400" data-legende="Discussion">
                    <div class="dj-chat">
                        <div class="dj-bubble dj-in dj-el" style="--d:.05s">Tu me manques 🫠</div>
                        <div class="dj-bubble dj-out dj-el" style="--d:.35s">Moi aussi. Tu as mangé ?</div>
                        <div class="dj-bubble dj-in dj-media dj-el" style="--d:.65s">
                            <span class="dj-media-ico">📷</span>
                            <span>Photo envoyée</span>
                        </div>
                        <div class="dj-bubble dj-out dj-el" style="--d:.95s">😍 Trop bien ici !</div>
                        <div class="dj-typing dj-in dj-el" style="--d:1.3s"><i></i><i></i><i></i></div>
                    </div>
                    <div class="dj-chips dj-el dj-up" style="--d:.4s">
                        <span class="dj-chip">🔒 Privé</span>
                        <span class="dj-chip">🎤 Vocaux</span>
                        <span class="dj-chip">🎬 Vidéos</span>
                        <span class="dj-chip">🎨 Stickers &amp; GIF</span>
                        <span class="dj-chip">⭐ Favoris</span>
                    </div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">Une messagerie rien que pour vous</div>
                        <div class="dj-cap-d">Messages, photos, vidéos, vocaux, stickers et GIF — votre discussion privée, avec la personne qui répond.</div>
                    </div>
                </div>

                {{-- 3 — Les 14 jeux : une scène par jeu, avec un aperçu animé du jeu --}}
                @foreach ($jeux as $j => [$ico, $nom, $desc, $cls])
                    <div class="dj-scene" data-scene="jeu-{{ $j }}" data-duree="{{ $durees['jeu'] }}" data-legende="{{ $ico }} {{ $nom }}">
                        <div class="dj-jeu-tete dj-el dj-up" style="--d:.04s">
                            <span class="dj-jeu-badge {{ $cls }}">{{ $ico }}</span>
                            <span class="dj-jeu-titre">{{ $nom }}</span>
                        </div>

                        <div class="dj-apercu dj-el dj-pop" style="--d:.1s">
                            @switch($j)
                                @case(0)
                                    <div class="dj-ap dj-ap-chat">
                                        <div class="dj-bubble dj-in dj-el" style="--d:.5s">Tu me manques 🫠</div>
                                        <div class="dj-bubble dj-out dj-el" style="--d:.85s">Moi aussi ❤️</div>
                                        <div class="dj-typing dj-in dj-el" style="--d:1.2s"><i></i><i></i><i></i></div>
                                    </div>
                                    @break

                                @case(1)
                                    <div class="dj-ap">
                                        <div class="dj-flip dj-fade">
                                            <div class="dj-flip-f">VÉRITÉ</div>
                                            <div class="dj-flip-b">ACTION</div>
                                        </div>
                                        <div class="dj-ap-choix">
                                            <span class="dj-ap-bouton dj-el" style="--d:1.5s">Vérité</span>
                                            <span class="dj-ap-bouton dj-ap-pick dj-el" style="--d:1.75s">Action</span>
                                        </div>
                                    </div>
                                    @break

                                @case(2)
                                    <div class="dj-ap">
                                        <div class="dj-ap-q">10 questions pour se tester</div>
                                        <div class="dj-ap-jauge"><i class="dj-el" style="--d:.6s"></i></div>
                                        <div class="dj-ap-choix">
                                            <span class="dj-ap-bouton dj-el" style="--d:.5s">OUI</span>
                                            <span class="dj-ap-bouton dj-ap-pick dj-el" style="--d:1s">NON</span>
                                        </div>
                                    </div>
                                    @break

                                @case(3)
                                    <div class="dj-ap">
                                        <div class="dj-carte">
                                            <span class="dj-carte-t">MISSION #003</span>
                                            <span class="dj-carte-c">Confier un secret à l'autre</span>
                                            <span class="dj-bandeau dj-fade" style="--d:1.1s">CLASSÉ</span>
                                            <span class="dj-carte-s dj-el" style="--d:1.5s">🔒 Vu par 1 / 2</span>
                                        </div>
                                    </div>
                                    @break

                                @case(4)
                                    <div class="dj-ap dj-ap-env">
                                        <div class="dj-env dj-env-r dj-fade" style="--d:.4s">💌</div>
                                        <div class="dj-env dj-env-b dj-fade" style="--d:.55s">
                                            💌<span class="dj-env-coeur dj-fade" style="--d:1.3s">💗</span>
                                        </div>
                                        <div class="dj-env dj-env-v dj-fade" style="--d:.4s">💌</div>
                                    </div>
                                    @break

                                @case(5)
                                    <div class="dj-ap">
                                        <div class="dj-ap-q dj-el" style="--d:.4s">🍕 Ton plat préféré ?</div>
                                        <div class="dj-ap-ligne dj-ap-ok dj-el" style="--d:.9s"><span>Pizza</span><i>✓</i></div>
                                        <div class="dj-ap-ligne dj-ap-ko dj-el" style="--d:1.2s"><span>Salade</span><i>✗</i></div>
                                    </div>
                                    @break

                                @case(6)
                                    <div class="dj-ap">
                                        <div class="dj-ap-q dj-el" style="--d:.4s">Qui a dit « j'adore l'action » ?</div>
                                        <div class="dj-avs">
                                            <span class="dj-av dj-av-a1 dj-el dj-left" style="--d:.7s">A</span>
                                            <span class="dj-av dj-av-a2 dj-el dj-right" style="--d:.7s">B</span>
                                        </div>
                                        <div class="dj-ap-pts dj-el dj-pop" style="--d:1.5s">+5 pts</div>
                                    </div>
                                    @break

                                @case(7)
                                    <div class="dj-ap">
                                        <div class="dj-ap-emoji dj-el dj-pop" style="--d:.3s">🌅</div>
                                        <div class="dj-ap-q dj-el" style="--d:.55s">Quelle est la plus belle ville ?</div>
                                        <div class="dj-reps">
                                            <span class="dj-rep dj-fade" style="--d:1s">Rome</span>
                                            <span class="dj-rep dj-fade" style="--d:1.35s">Lisbonne</span>
                                        </div>
                                        <div class="dj-ap-accord dj-el dj-pop" style="--d:1.75s">✓ accord</div>
                                    </div>
                                    @break

                                @case(8)
                                    <div class="dj-ap">
                                        <div class="dj-ciel dj-fade" style="--d:.3s"><span>🌧️</span><span>⛅</span><span>🔥</span></div>
                                        <div class="dj-ap-jauge dj-ap-jauge-meteo"><i class="dj-el" style="--d:.5s"></i></div>
                                        <div class="dj-ap-poles dj-el" style="--d:.7s"><span>Morose</span><span>Amoureux</span></div>
                                    </div>
                                    @break

                                @case(9)
                                    <div class="dj-ap">
                                        <div class="dj-grille">
                                            <span class="dj-case dj-el" style="--d:.5s">D</span>
                                            <span class="dj-case dj-el" style="--d:.65s">J</span>
                                            <span class="dj-case dj-el" style="--d:.8s">S</span>
                                            <span class="dj-case dj-case-vide"></span>
                                            <span class="dj-case dj-el" style="--d:.95s">A</span>
                                            <span class="dj-case dj-case-vide"></span>
                                            <span class="dj-case dj-case-vide"></span>
                                            <span class="dj-case dj-el" style="--d:1.1s">M</span>
                                            <span class="dj-case dj-case-vide"></span>
                                        </div>
                                    </div>
                                    @break

                                @case(10)
                                    <div class="dj-ap">
                                        <div class="dj-lignes">
                                            <div class="dj-ligne dj-el" style="--d:.5s"><span class="dj-ligne-boite">✓</span>Voir l'aurore boréale</div>
                                            <div class="dj-ligne dj-el" style="--d:.8s"><span class="dj-ligne-boite">✓</span>Apprendre une danse</div>
                                            <div class="dj-ligne dj-ligne-reste dj-el" style="--d:1.1s"><span class="dj-ligne-boite"></span>Voyager sans argent</div>
                                        </div>
                                    </div>
                                    @break

                                @case(11)
                                    <div class="dj-ap">
                                        <div class="dj-mois dj-el" style="--d:.35s">Mars</div>
                                        <div class="dj-grille dj-grille-cal">
                                            @for ($c = 1; $c <= 14; $c++)
                                                <span class="dj-case {{ in_array($c, [9, 10], true) ? 'dj-case-rdv' : '' }} dj-el" style="--d:{{ sprintf('%.2f', 0.5 + $c * 0.04) }}s"></span>
                                            @endfor
                                        </div>
                                        <div class="dj-ap-puce dj-el dj-pop" style="--d:1.5s">🗓️ 19h · Dîner surprise</div>
                                    </div>
                                    @break

                                @case(12)
                                    <div class="dj-ap">
                                        <div class="dj-de dj-fade" style="--d:.35s">🎲</div>
                                        <div class="dj-piste dj-el" style="--d:.6s">
                                            <span class="dj-piste-case dj-el" style="--d:.8s"></span>
                                            <span class="dj-piste-case dj-el" style="--d:.95s"></span>
                                            <span class="dj-piste-case dj-el" style="--d:1.1s"></span>
                                            <span class="dj-piste-case dj-el" style="--d:1.25s"></span>
                                            <span class="dj-pion dj-fade" style="--d:1.4s"></span>
                                        </div>
                                    </div>
                                    @break

                                @case(13)
                                    <div class="dj-ap">
                                        <div class="dj-plateau dj-el" style="--d:.4s">
                                            @for ($c = 1; $c <= 12; $c++)
                                                <span class="dj-case"></span>
                                            @endfor
                                            <span class="dj-quo-mur dj-fade" style="--d:1.2s"></span>
                                            <span class="dj-quo-pion dj-fade" style="--d:.6s"></span>
                                        </div>
                                        <div class="dj-ap-puce dj-el dj-pop" style="--d:1.7s">⛔ Barrière !</div>
                                    </div>
                                    @break
                            @endswitch
                        </div>

                        <div class="dj-jeu-desc dj-el dj-up" style="--d:.2s">{{ $desc }}</div>
                    </div>
                @endforeach

                {{-- 4 — La journée type --}}
                <div class="dj-scene" data-scene="journee" data-duree="4200" data-legende="La journée">
                    <div class="dj-el dj-up" style="--d:.1s;font-size:15px;font-weight:700">Le rituel quotidien</div>
                    <div class="dj-tl dj-el" style="--d:.2s">
                        <div class="dj-tl-line"><div class="dj-tl-run"></div></div>
                        <div class="dj-tl-row dj-el" style="--d:.3s">
                            <span class="dj-tl-dot"></span><span class="dj-tl-h">00h</span><span>🕵️</span><b>Une mission secrète tombe</b>
                        </div>
                        <div class="dj-tl-row dj-el" style="--d:.6s">
                            <span class="dj-tl-dot"></span><span class="dj-tl-h">08h</span><span>🌅</span><b>La question du jour</b>
                        </div>
                        <div class="dj-tl-row dj-el" style="--d:.9s">
                            <span class="dj-tl-dot"></span><span class="dj-tl-h">12h</span><span>🌦️</span><b>Météo du couple</b>
                        </div>
                        <div class="dj-tl-row dj-el" style="--d:1.2s">
                            <span class="dj-tl-dot"></span><span class="dj-tl-h">20h</span><span>🕵️</span><b>Verdict de la mission</b>
                        </div>
                    </div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">Le soir, on se retrouve</div>
                        <div class="dj-cap-d">Une notification vous previent : l'autre a répondu, une mission s'est terminée.</div>
                    </div>
                </div>

                {{-- 5 — Points et série --}}
                <div class="dj-scene" data-scene="points" data-duree="4200" data-legende="Les points">
                    <div class="dj-score dj-el dj-up" style="--d:.1s">
                        <div class="dj-score-n" id="dj-score-n" data-cible="1240">0</div>
                        <div class="dj-score-l">points pour le couple</div>
                    </div>
                    <div class="dj-jauge dj-el" style="--d:.2s;--pct:100%"><i></i></div>
                    <div class="dj-chips dj-el dj-down" style="--d:.35s">
                        <span class="dj-streak">🔥 12 jours d'affilée</span>
                    </div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">Tout compte</div>
                        <div class="dj-cap-d">Accord trouvé, question du jour, victoire au Quoridor : chaque geste fait monter le compteur.</div>
                    </div>
                </div>

                {{-- 6 — Les récompenses --}}
                <div class="dj-scene" data-scene="recompenses" data-duree="4200" data-legende="Récompenses">
                    <div class="dj-el dj-up" style="--d:.1s;font-size:15px;font-weight:700">Les paliers du couple</div>
                    <div class="dj-th dj-el" style="--d:.2s">
                        <div class="dj-th-row dj-el" style="--d:.3s">✅ Massage <span>100 pts</span></div>
                        <div class="dj-th-row dj-el" style="--d:.5s">✅ Dîner surprise <span>250 pts</span></div>
                        <div class="dj-th-row dj-el" style="--d:.7s">✅ Exaucer un souhait <span>500 pts</span></div>
                        <div class="dj-th-row dj-el" style="--d:.9s">✅ Récompense personnalisée <span>1 000 pts</span></div>
                    </div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">À vous de choisir les lots</div>
                        <div class="dj-cap-d">Créez vos propres récompenses : l'app vous dit lesquelles sont débloquées, et qui doit offrir.</div>
                    </div>
                </div>

                {{-- 7 — L'app installable --}}
                <div class="dj-scene" data-scene="app" data-duree="4600" data-legende="L'app">
                    <div class="dj-app-logo dj-el dj-pop" style="--d:.05s">
                        <img src="{{ asset('icons/icon-512.png') }}" alt="Double Jeu" width="112" height="112">
                    </div>
                    <div class="dj-app-nom dj-el dj-up" style="--d:.2s">Double Jeu</div>
                    <div class="dj-app-note dj-el dj-up" style="--d:.28s">Une touche pour jouer, même hors ligne</div>
                    <button type="button" class="dj-app-btn dj-el dj-pop" id="dj-install" style="--d:.4s">
                        <span aria-hidden="true">⬇</span> Installer l'application
                    </button>
                    <div class="dj-app-hint dj-el dj-up" style="--d:.55s">Sur iPhone : Partager ▸ Sur l'écran d'accueil</div>
                    <div class="dj-chips dj-el dj-up" style="--d:.7s">
                        <span class="dj-chip">📱 iOS &amp; Android</span>
                        <span class="dj-chip">💻 Ordinateur</span>
                        <span class="dj-chip">✈️ Hors ligne</span>
                        <span class="dj-chip">🔔 Notifications</span>
                    </div>
                    <div class="dj-cap">
                        <div class="dj-cap-t">Toujours dans la poche</div>
                        <div class="dj-cap-d">Installable en un geste, elle fonctionne même sans réseau et prévient dès que l'autre joue.</div>
                    </div>
                </div>
            </div>

            <button type="button" class="dj-playbtn" id="dj-play">
                <span class="dj-playbtn-ico">▶</span>
                <span class="dj-playbtn-l">Voir la présentation</span>
                <span class="dj-playbtn-s">{{ $dureeLisible }} · {{ $nbScenes }} scènes</span>
            </button>

            <div class="dj-bar">
                <button type="button" class="dj-toggle" id="dj-toggle" aria-label="Lecture">▶</button>
                <div class="dj-track" id="dj-track">
                    <div class="dj-fill" id="dj-fill"></div>
                </div>
                <span class="dj-time" id="dj-time">{{ $chrono }}</span>
            </div>
        </div>

        <div class="dj-legende" id="dj-legende"></div>
    </section>

    <section class="card center pad-lg" style="margin-top:28px">
        <h2 class="section-title">Comment ça marche ?</h2>
        <div class="steps">
            <div><span class="step-n">1</span><p>Inscrivez-vous chacun·e</p></div>
            <div><span class="step-n">2</span><p>Générez un code, liez votre couple</p></div>
            <div><span class="step-n">3</span><p>Jouez à distance, gagnez des points</p></div>
            <div><span class="step-n">4</span><p>Débloquez des récompenses 🎁</p></div>
        </div>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-block" style="margin-top:18px">Accéder à mon tableau de bord</a>
        @else
            <a href="{{ route('register') }}" class="btn btn-primary btn-block" style="margin-top:18px">Commencer maintenant</a>
        @endauth
    </section>
@endsection

@push('scripts')
    <script>
        /* Lecteur de la présentation : une seule lecture, du début à la fin, sans intervention.
           La position est dérivée de l'horloge (comme une vidéo) et non cumulée frame par frame :
           si le navigateur ralentit ou gèle une frame, la lecture rattrape son retard au lieu de stopper.
           Une boucle de surveillance relance requestAnimationFrame s'il se met à fenêtre. */
        (function () {
            const player = document.getElementById('dj-player');
            if (!player) return;

            const scenes = Array.from(player.querySelectorAll('.dj-scene'));
            if (!scenes.length) return;

            const durees = scenes.map((s) => Math.max(1200, parseInt(s.dataset.duree, 10) || 3200));
            const starts = [];
            durees.reduce((acc, d, i) => { starts[i] = acc; return acc + d; }, 0);
            const total = starts[starts.length - 1] + durees[durees.length - 1];
            const indexScore = scenes.findIndex((s) => s.dataset.scene === 'points');

            const fill = document.getElementById('dj-fill');
            const timeEl = document.getElementById('dj-time');
            const playBtn = document.getElementById('dj-play');
            const playLabel = playBtn ? playBtn.querySelector('.dj-playbtn-l') : null;
            const toggleBtn = document.getElementById('dj-toggle');
            const track = document.getElementById('dj-track');
            const legende = document.getElementById('dj-legende');
            const scoreEl = document.getElementById('dj-score-n');
            const scoreCible = scoreEl ? parseInt(scoreEl.dataset.cible, 10) || 0 : 0;

            const chapitres = scenes.map((s, i) => {
                const start = starts[i];
                const libelle = s.dataset.legende || String(i + 1);

                const tick = document.createElement('span');
                tick.className = 'dj-tick';
                tick.style.left = (start / total) * 100 + '%';
                if (track) track.appendChild(tick);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dj-chap';
                btn.textContent = libelle;
                btn.title = 'Revoir : ' + libelle;
                btn.addEventListener('click', () => seek(start));
                if (legende) legende.appendChild(btn);

                return { tick, btn };
            });

            /* Position : « pos » est figée, « ancre » est l'heure correspondante. */
            let pos = 0;
            let ancre = 0;
            let courant = -1;
            let raf = null;
            let derniereFrame = 0;
            let repriseAuto = false;

            const maintenant = () => (window.performance && performance.now ? performance.now() : Date.now());
            const position = (t) => Math.min(total, pos + Math.max(0, t - ancre));
            const enLecture = () => player.dataset.state === 'playing';

            const fmt = (ms) => {
                const s = Math.round(ms / 1000);
                return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            };

            const indexAt = (t) => {
                for (let i = starts.length - 1; i >= 0; i--) {
                    if (t >= starts[i]) return i;
                }
                return 0;
            };

            const easeOut = (p) => 1 - Math.pow(1 - p, 3);

            function setState(state) {
                player.dataset.state = state;
                const playing = state === 'playing';
                if (toggleBtn) {
                    toggleBtn.textContent = playing ? '❚❚' : '▶';
                    toggleBtn.setAttribute('aria-label', playing ? 'Pause' : 'Lecture');
                }
                if (playLabel) playLabel.textContent = state === 'ended' ? 'Rejouer la présentation' : 'Voir la présentation';
            }

            function montrer(index) {
                scenes.forEach((s, i) => s.classList.toggle('is-on', i === index));
                chapitres.forEach((c, i) => {
                    c.btn.classList.toggle('on', i === index);
                    c.tick.classList.toggle('on', i <= index);
                });
                /* La légende défile toute seule pour garder le chapitre actif visible. */
                const actif = chapitres[index] && chapitres[index].btn;
                if (actif && typeof actif.scrollIntoView === 'function') {
                    actif.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
                courant = index;
            }

            function afficher(t) {
                const index = indexAt(t);
                if (index !== courant) montrer(index);
                if (fill) fill.style.width = (t / total) * 100 + '%';
                if (timeEl) timeEl.textContent = fmt(t) + ' / ' + fmt(total);
                if (scoreEl && index === indexScore) {
                    const local = t - starts[index];
                    scoreEl.textContent = scoreCible ? Math.round(scoreCible * easeOut(Math.min(1, local / 1600))) : 0;
                }
            }

            /* Le rendu ne doit jamais interrompre la lecture : on isole les écritures DOM. */
            function rendre(t) {
                try {
                    afficher(t);
                } catch (e) {
                    if (window.console) console.error('[dj-player]', e);
                }
            }

            function frame() {
                derniereFrame = maintenant();
                const t = position(derniereFrame);
                rendre(t);
                if (t >= total) {
                    setState('ended');
                    raf = null;
                    return;
                }
                raf = window.requestAnimationFrame(frame);
            }

            function relancer() {
                if (raf !== null) window.cancelAnimationFrame(raf);
                derniereFrame = maintenant();
                raf = window.requestAnimationFrame(frame);
            }

            function play() {
                if (player.dataset.state === 'ended') {
                    pos = 0;
                    courant = -1;
                }
                ancre = maintenant();
                setState('playing');
                /* Pendant la motion, l'appel à l'installation, c'est la dernière scène : pas de bannière. */
                if (window.djInstallPrompt && typeof window.djInstallPrompt.dismiss === 'function') window.djInstallPrompt.dismiss();
                relancer();
            }

            function pause() {
                if (enLecture()) pos = position(maintenant());
                if (raf !== null) window.cancelAnimationFrame(raf);
                raf = null;
                setState('paused');
            }

            function seek(t) {
                pos = Math.max(0, Math.min(total, t));
                ancre = maintenant();
                courant = -1;
                if (player.dataset.state === 'idle' || player.dataset.state === 'ended') setState('paused');
                rendre(pos);
            }

            function basculer() {
                if (enLecture()) pause(); else play();
            }

            if (playBtn) playBtn.addEventListener('click', play);
            if (toggleBtn) toggleBtn.addEventListener('click', basculer);
            if (track) {
                track.addEventListener('click', (e) => {
                    const rect = track.getBoundingClientRect();
                    if (rect.width) seek(((e.clientX - rect.left) / rect.width) * total);
                });
            }

            /* Onglet caché : on met en pause comme un lecteur vidéo, et on reprend au retour. */
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    repriseAuto = enLecture();
                    if (repriseAuto) pause();
                } else if (repriseAuto) {
                    repriseAuto = false;
                    play();
                }
            });

            /* Le bouton « Installer » de la dernière scène passe au-dessus de l'overlay de lecture. */
            const installBtn = document.getElementById('dj-install');
            if (installBtn) {
                installBtn.addEventListener('click', () => {
                    const prompt = window.djInstallPrompt;
                    if (prompt && prompt.prompt()) return;
                    if (window.installPwa && window.installPwa.showIosGuide) window.installPwa.showIosGuide(true);
                });
            }

            /* Filet de sécurité : si rAF se met à fenêtre (onglet en arrière-plan, mode éco…),
               on le relance. Sans ça la lecture resterait figée jusqu'au clic suivant. */
            setInterval(() => {
                if (enLecture() && maintenant() - derniereFrame > 800) relancer();
            }, 400);

            montrer(0);
            afficher(0);
        })();
    </script>
@endpush
