@extends('layouts.app')

@section('title', 'Tableau de bord')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="fadeIn">
        {{-- Couple header --}}
        <div class="hero-card">
            <span class="hero-float hero-float-1" aria-hidden="true">♥</span>
            <span class="hero-float hero-float-2" aria-hidden="true">♥</span>
            <span class="hero-float hero-float-3" aria-hidden="true">♥</span>
            <div class="hero-inner">
                <div class="hero-text">
                    <div class="hero-bienvenue">
                        Bienvenue<span class="hero-bienvenue-dash"></span><span class="hero-bienvenue-coeur" aria-hidden="true">♡</span>
                    </div>
                    <div class="hero-names">
                        {{ $me->name }}<br>
                        <span class="hero-amp">&</span> {{ $partner->name }}
                    </div>
                    <div class="hero-sub">Votre duo. Votre histoire.</div>
                </div>
                <div class="hero-avatars">
                    <span class="hero-ring hero-ring-moi"><x-avatar :user="$me" class="lg" /></span>
                    <span class="hero-heart">💗</span>
                    <span class="hero-ring hero-ring-them"><x-avatar :user="$partner" class="lg" /></span>
                </div>
            </div>
        </div>

        @if ($annivMoi['cadeauDuJour'])
            <a href="{{ route('anniversaire.ouvrir') }}" class="aniv-pill aniv-pill-cadeau hero-cadeau">
                🎁 {{ $partner->name }} t'a préparé quelque chose
            </a>
        @elseif ($annivPartenaire['peutCelebrer'])
            <a href="{{ route('anniversaire.celebrer') }}" id="hero-celebrer"
                class="btn btn-sm btn-primary hero-celebrer pulse-glow">
                {{ $annivPartenaire['celebration'] ? '🎂 Modifier son cadeau' : '🎂 Célébrer son anniversaire' }}
            </a>
        @endif

        @php $anivs = array_values(array_filter([$annivMoi, $annivPartenaire])); @endphp
        @if ($anivs)
            <div class="aniv-list">
                @foreach ($anivs as $anniv)
                    @if ($anniv['jours'] === 0)
                        {{-- Jour J : une seule ligne à la place de la fiche.
                             Le lien cadeau, s'il y en a un, est dans le hero. --}}
                        <div class="aniv-row aniv-row-jourj">
                            🎉 C'est l'anniversaire de {{ $anniv['name'] }} !
                        </div>
                    @else
                        <div class="aniv-card {{ $loop->index % 2 === 0 ? 'aniv-card-rose' : 'aniv-card-bleu' }}"
                            data-anniversaire="{{ $anniv['name'] }}">
                            <div class="aniv-icon" aria-hidden="true">
                                🎂@unless ($loop->first)<span class="aniv-icon-coeur">❤️</span>@endunless
                            </div>
                            <div class="aniv-body">
                                <div class="aniv-label">Anniversaire de</div>
                                <div class="aniv-name">{{ $anniv['name'] }}</div>
                            </div>
                            <div class="aniv-date">
                                @if ($anniv['date'])
                                    <span class="aniv-date-val">
                                        <svg class="aniv-date-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                                        {{ $anniv['date']->translatedFormat('l j F Y') }}
                                    </span>
                                @else
                                    Date de naissance à renseigner sur le profil
                                @endif
                            </div>
                            <div class="aniv-actions">
                                <div class="aniv-pill">
                                    @if ($anniv['jours'] === null)
                                        <span class="tiny muted">—</span>
                                    @else
                                        J - {{ $anniv['jours'] }} jours
                                    @endif
                                </div>
                                <span class="aniv-chevron" aria-hidden="true">›</span>
                            </div>
                            <span class="aniv-deco" aria-hidden="true">♡ ♡</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Météo du couple --}}
        <section class="section-head">
            <h2>💞 Météo du couple</h2>
            <a href="{{ route('meteo.index') }}" class="meteo-share">Partager la mienne →</a>
        </section>

        @if ($meteoSynthese)
            <div class="meteo-syn">
                <div class="meteo-syn-illus" aria-hidden="true">
                    💗<span class="meteo-syn-illus-meteo">{{ $meteoSynthese['emoji'] }}</span>
                </div>
                <div class="meteo-syn-txt">
                    <div class="meteo-syn-kicker">Notre météo aujourd'hui</div>
                    <div class="meteo-syn-title">{{ $meteoSynthese['label'] }}</div>
                    <div class="meteo-syn-desc">{{ $meteoSynthese['description'] }}</div>
                </div>
                @if ($meteoTaux !== null)
                    <div class="meteo-gauge" style="--pct: {{ $meteoTaux }}">
                        <div class="meteo-gauge-inner">
                            <strong>{{ $meteoTaux }}%</strong>
                            <span>d'amour ❤</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="meteo-duo">
            <div class="meteo-person{{ $meteoMoi ? '' : ' meteo-person-empty' }}">
                <div class="meteo-person-top">
                    <x-avatar :user="$me" class="sm" />
                    <span class="meteo-person-name">{{ $me->name }} 👑</span>
                </div>
                @if ($meteoMoi)
                    <div class="meteo-person-emoji">
                        <span class="mood-anim" data-lottie="{{ $meteoMoi['lottie'] }}">{{ $meteoMoi['emoji'] }}</span>
                    </div>
                    <div class="meteo-person-label">{{ $meteoMoi['label'] }}</div>
                @else
                    <a href="{{ route('meteo.index') }}" class="meteo-person-heart" aria-label="Partager ma météo">♡</a>
                    <div class="meteo-person-label muted">Pas encore partagée</div>
                @endif
            </div>
            <div class="meteo-person{{ $meteoPartenaire ? '' : ' meteo-person-empty' }}">
                <div class="meteo-person-top">
                    <x-avatar :user="$partner" class="sm" />
                    <span class="meteo-person-name">{{ $partner->name }} 👑</span>
                </div>
                @if ($meteoPartenaire)
                    <div class="meteo-person-emoji">
                        <span class="mood-anim" data-lottie="{{ $meteoPartenaire['lottie'] }}">{{ $meteoPartenaire['emoji'] }}</span>
                    </div>
                    <div class="meteo-person-label">{{ $meteoPartenaire['label'] }}</div>
                @else
                    <a href="{{ route('meteo.index') }}" class="meteo-person-heart" aria-label="Voir la météo du couple">♡</a>
                    <div class="meteo-person-label muted">Pas encore partagée</div>
                @endif
            </div>
        </div>

        <a href="{{ route('meteo.index') }}" class="meteo-cta">
            <span class="meteo-cta-emoji">⛅</span>
            <span class="meteo-cta-txt">
                <strong>Partagez votre météo du jour</strong>
                <small>Découvrez comment vous vous sentez</small>
            </span>
            <span class="meteo-cta-arrow" aria-hidden="true">→</span>
        </a>

        {{-- Les jeux --}}
        <section id="jeux">
            <div class="jeux-banner">
                <div class="jeux-banner-txt">
                    <div class="jeux-kicker">LES JEUX 🎮</div>
                    <h2 class="jeux-title">Jouez, riez,<br><span class="jeux-title-accent">découvrez-vous 💕</span></h2>
                    <p class="jeux-sub">Des jeux pensés pour vous,<br>pour des moments inoubliables.</p>
                </div>
                <div class="jeux-banner-deco" aria-hidden="true">
                    <span class="jeux-script">Ensemble,<br>c'est mieux 💕</span>
                </div>
            </div>

            <div class="jeux-filters">
                <span class="jeux-chip is-active">🔥 Tous</span>
                <span class="jeux-chip">❤️ Romance</span>
                <span class="jeux-chip">😂 Drôle</span>
                <span class="jeux-chip">🧠 Profond</span>
                <span class="jeux-chip">🎯 Défi</span>
                <span class="jeux-chip">🎁 Surprise</span>
            </div>

            <div class="jeux-pop-head">
                <h3>🔥 Nos jeux populaires</h3>
                <a href="#jeux" class="jeux-voir">Voir tout →</a>
            </div>

            <div class="jeux-grid">
                <a href="{{ route('vo.index') }}" class="jeux-card cat-romance fadeIn" style="animation-delay:.01s">
                    <div class="jc-emoji">🎭</div>
                    <div class="jc-body">
                        <div class="jc-name">Vérité ou Action</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">❤️ Romance</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('ouinon.index') }}" class="jeux-card cat-drole fadeIn" style="animation-delay:.03s">
                    <div class="jc-emoji">⚖️</div>
                    <div class="jc-body">
                        <div class="jc-name">Oui ou Non</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">😂 Drôle</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('quiz.index') }}" class="jeux-card cat-profond fadeIn" style="animation-delay:.05s">
                    <div class="jc-emoji">❓</div>
                    <div class="jc-body">
                        <div class="jc-name">Tu me connais ?</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🧠 Profond</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('mission.index') }}" class="jeux-card cat-defi fadeIn" style="animation-delay:.07s">
                    <div class="jc-emoji">🕵️</div>
                    <div class="jc-body">
                        <div class="jc-name">Mission secrète</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🎯 Défi</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('bucket-list.index') }}" class="jeux-card cat-surprise fadeIn" style="animation-delay:.09s">
                    <div class="jc-emoji">🧳</div>
                    <div class="jc-body">
                        <div class="jc-name">Bucket List</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🎁 Surprise</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('discussion.index') }}" class="jeux-card cat-discussion fadeIn" style="animation-delay:.11s">
                    <div class="jc-emoji">💬</div>
                    <div class="jc-body">
                        <div class="jc-name">Discussion</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">💬 Discussion</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('enveloppe.index') }}" class="jeux-card cat-romance fadeIn" style="animation-delay:.13s">
                    <div class="jc-emoji">💌</div>
                    <div class="jc-body">
                        <div class="jc-name">Enveloppes</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">❤️ Romance</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('qdn2.index') }}" class="jeux-card cat-drole fadeIn" style="animation-delay:.15s">
                    <div class="jc-emoji">🙋</div>
                    <div class="jc-body">
                        <div class="jc-name">Qui de nous deux ?</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">😂 Drôle</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('question.index') }}" class="jeux-card cat-profond fadeIn" style="animation-delay:.17s">
                    <div class="jc-emoji">🌅</div>
                    <div class="jc-body">
                        <div class="jc-name">Question du jour</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🧠 Profond</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('meteo.index') }}" class="jeux-card cat-profond fadeIn" style="animation-delay:.19s">
                    <div class="jc-emoji">🌦️</div>
                    <div class="jc-body">
                        <div class="jc-name">Météo du couple</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🧠 Profond</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('mots-croises.index') }}" class="jeux-card cat-defi fadeIn" style="animation-delay:.21s">
                    <div class="jc-emoji">🧩</div>
                    <div class="jc-body">
                        <div class="jc-name">Mots croisés</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🎯 Défi</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('calendrier.index') }}" class="jeux-card cat-profond fadeIn" style="animation-delay:.23s">
                    <div class="jc-emoji">🗓️</div>
                    <div class="jc-body">
                        <div class="jc-name">Calendrier</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🧠 Profond</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('ludo.index') }}" class="jeux-card cat-drole fadeIn" style="animation-delay:.25s">
                    <div class="jc-emoji">🎲</div>
                    <div class="jc-body">
                        <div class="jc-name">Ludo à deux</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">😂 Drôle</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
                <a href="{{ route('quoridor.index') }}" class="jeux-card cat-defi fadeIn" style="animation-delay:.27s">
                    <div class="jc-emoji">🧱</div>
                    <div class="jc-body">
                        <div class="jc-name">Quoridor</div>
                    </div>
                    <div class="jc-tags">
                        <span class="jc-tag">🎯 Défi</span>
                        <span class="jc-tag">👥 2 joueurs</span>
                    </div>
                    <span class="jc-arrow" aria-hidden="true">›</span>
                </a>
            </div>

            <div class="jeux-cta">
                <div class="jc-cta-emoji" aria-hidden="true">🎮</div>
                <div class="jc-cta-txt">
                    <strong>Envie de plus de fun ?</strong>
                    <small>Découvrez tous nos jeux et vivez des moments uniques.</small>
                </div>
                <a href="#jeux" class="jc-cta-btn">Explorer tous les jeux →</a>
            </div>
        </section>

        {{-- Activité du couple --}}
        <section class="section-head"><h2>Activité</h2></section>
        <div class="card pad-sm">
            <div class="row">
                <x-avatar :user="$me" class="sm" />
                <div class="grow">
                    <strong>{{ $me->name }}</strong>
                    <div class="tiny" id="ligne-moi">
                        @if ($me->last_active_at && $me->last_active_at->diffInMinutes() < 1)
                            <span style="color:var(--success)">● en ligne</span>
                        @elseif ($me->last_active_at)
                            <span class="muted">En ligne il y'a {{ $me->last_active_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                        @else
                            <span class="muted">Pas encore en ligne aujourd'hui</span>
                        @endif
                    </div>
                </div>
                @if ($me->last_active_at && $me->last_active_at->isToday())
                    <span class="badge succes">aujourd'hui</span>
                @endif
            </div>
            <div class="row" style="border-bottom:none">
                <x-avatar :user="$partner" class="sm" />
                <div class="grow">
                    <strong>{{ $partner->name }}</strong>
                    <div class="tiny" id="ligne-partenaire">
                        @if ($partner->last_active_at && $partner->last_active_at->diffInMinutes() < 1)
                            <span style="color:var(--success)">● en ligne</span>
                        @elseif ($partner->last_active_at)
                            <span class="muted">En ligne il y'a {{ $partner->last_active_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                        @else
                            <span class="muted">En attente de connexion…</span>
                        @endif
                    </div>
                </div>
                @if ($partner->last_active_at && $partner->last_active_at->isToday())
                    <span class="badge succes">aujourd'hui</span>
                @endif
            </div>
        </div>

        {{-- Missions secrètes en cours --}}
        <section class="section-head">
            <h2>Missions secrètes</h2>
            <a href="{{ route('mission.index') }}" class="tiny">voir tout →</a>
        </section>
        <div class="card pad-sm">
            @if ($missionsEnCours > 0)
                <div class="flex between items-center">
                    <div>
                        <strong>{{ $missionsEnCours }} mission(s) en cours</strong>
                        <div class="tiny muted">Dans la pénombre… 🕵️</div>
                    </div>
                    <a href="{{ route('mission.index') }}" class="btn btn-sm btn-soft">Y aller</a>
                </div>
            @else
                <div class="flex between items-center">
                    <div>
                        <strong>Aucune mission en cours</strong>
                        <div class="tiny muted">Une nouvelle mission t'attend chaque jour à 00h</div>
                    </div>
                    <a href="{{ route('mission.index') }}" class="btn btn-sm btn-soft">🕵️</a>
                </div>
            @endif
        </div>

        {{-- Missions Oui/Non à réaliser --}}
        <section class="section-head">
            <h2>Missions du Oui/Non</h2>
            <a href="{{ route('ouinon.index') }}" class="tiny">voir tout →</a>
        </section>
        <div class="card pad-sm">
            @forelse ($missionsOuiNon->take(3) as $mission)
                <div class="row">
                    <div class="grow">
                        <div style="font-size:14px">{{ $mission->question->texte }}</div>
                        <small>Mission à réaliser ensemble 🤝</small>
                    </div>
                </div>
            @empty
                <div class="tiny muted center" style="padding:8px">
                    Aucune mission validée pour l'instant. Joue à Oui/Non !
                </div>
            @endforelse
        </div>

        {{-- Récompenses --}}
        <section class="section-head">
            <h2>Récompenses</h2>
            <a href="{{ route('recompenses.index') }}" class="tiny">voir tout →</a>
        </section>
        <div class="card center">
            <div style="font-size:34px">🏆</div>
            <p class="muted mt8" style="margin-bottom:0">Gagnez des points ensemble. 100 pts = un massage, 250 = un dîner surprise…</p>
            <a href="{{ route('recompenses.index') }}" class="btn btn-sm btn-primary mt16">Voir les récompenses</a>
        </div>
    </div>

    @if ($octobreRose['due'])
        {{-- Sensibilisation Octobre rose : le 1er octobre, une fois par
             utilisateur et par année. Thème rose, ruban de solidarité. --}}
        <div id="octobre-rose" class="modal-ov rose-ov" style="display:none" role="dialog" aria-modal="true"
            aria-labelledby="octobre-rose-t">
            <div class="modal center rose-box">
                <div class="rose-ruban" aria-hidden="true"></div>
                <div class="rose-emoji" aria-hidden="true">🎀</div>
                <div class="rose-kicker">Octobre rose</div>
                <h3 id="octobre-rose-t">{{ $octobreRose['titre'] }}</h3>
                <p class="muted rose-texte">
                    Octobre est le mois de la sensibilisation au cancer du sein.
                    Au Sénégal, c'est le <strong>2e cancer le plus fréquent chez les
                    femmes</strong>. Un diagnostic précoce et un dépistage régulier,
                    c'est ce qui change le cours d'une histoire.
                </p>
                <div class="rose-ribbon" aria-hidden="true">
                    <span>Se demander : est-ce qu'on a fait son mammographie&nbsp;?</span>
                </div>
                <p class="rose-foot tiny">
                    Avertissement · Ce module est informatif et ne remplace pas un avis médical.
                    Parlez-en à votre médecin. · Source&nbsp;: GLOBOCAN (OMS / CIRC).
                </p>
                <button type="button" class="btn btn-rose btn-block" onclick="octobreRoseFermer()">
                    J'ai compris
                </button>
            </div>
        </div>
    @endif

    @if ($annivPartenaire['infoDue'])
        {{-- 1. Explication de la fonctionnalité, à l'ouverture de la fenêtre.
             Non fermable sur le fond : la seule sortie est le bouton
             "Où trouver ça ?", qui enchaîne sur la bulle. --}}
        <div id="anniv-info" class="modal-ov" style="display:none" role="dialog" aria-modal="true"
            aria-labelledby="anniv-info-t">
            <div class="modal center">
                <div style="font-size:44px; margin-bottom:6px">🎂</div>
                <h3 id="anniv-info-t">C'est bientôt l'anniversaire de {{ $annivPartenaire['name'] }} !</h3>
                <p class="muted" style="font-size:14.5px; line-height:1.55">
                    Tu as quelques jours pour lui préparer un cadeau surprise.
                    Écris-lui un mot, un son, une vidéo, une activité ou une promesse&nbsp;:
                    <strong>il/elle ne pourra rien voir ni rien modifier avant le jour J.</strong>
                </p>
                <div class="aniv-info-list">
                    <div><span>💌</span> Un mot personnel</div>
                    <div><span>🎧</span> Un son de ta voix</div>
                    <div><span>🎬</span> Une vidéo</div>
                    <div><span>🎁</span> Une idée d'activité</div>
                    <div><span>💍</span> Une promesse</div>
                </div>
                <button type="button" class="btn btn-primary btn-block mt16" onclick="annivInfoSuite()">
                    Où trouver ça&nbsp;? →
                </button>
            </div>
        </div>

{{-- 2. Indicateur : même composant .modal-ov/.modal que le 1er, avec une
             flèche en haut qui pointe vers le bouton Célébrer du hero. --}}
        <div id="anniv-tuto" class="modal-ov anniv-tuto-descend anniv-tuto-veil" style="display:none" role="dialog" aria-modal="true"
            aria-labelledby="anniv-tuto-t">
            <div class="modal center anniv-tuto-box">
                <div class="anniv-tuto-fleche"></div>
                <div class="anniv-tuto-doigt" aria-hidden="true">👆</div>
                <h3 id="anniv-tuto-t">C'est par ici !</h3>
                <p class="muted" style="font-size:14.5px; line-height:1.55; margin-bottom:0">
                    Le bouton <strong>🎂 Célébrer son anniversaire</strong>, en haut de cette page,
                    apparaît {{ $annivPartenaire['fenetreJours'] }} jours avant son anniversaire.
                    Tu peux revenir le modifier autant de fois que tu veux, jusqu'au jour J.
                </p>
                <button type="button" class="btn btn-primary btn-block mt16" onclick="annivTutoFermer()">
                    J'ai compris
                </button>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        {{-- Octobre rose : affiché seul le 1er octobre, et refermé
             définitivement après le clic de validation. --}}
        @if ($octobreRose['due'])
            function octobreRoseOuvrir() {
                document.getElementById('octobre-rose').style.display = 'flex';
            }

            function octobreRoseFermer() {
                document.getElementById('octobre-rose').style.display = 'none';
                fetch('{{ route('octobre-rose.info.vue') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({}),
                }).catch(() => {});
                // Le 1er octobre peut tomber dans la fenêtre d'un anniversaire :
                // les deux modals ne doivent jamais se superposer. Le tutoriel
                // d'anniversaire attend donc la fin de la sensibilisation.
                if (typeof annivInfoOuvrir === 'function') {
                    annivInfoOuvrir();
                }
            }
        @endif

        {{-- Le tutoriel d'anniversaire : 2 modals, dans l'ordre, une fois par année.
             Les deux réutilisent le composant .modal-ov/.modal, déjà utilisé
             partout. Le bouton Célébrer reste un lien normal : on n'intercepte
             jamais le clic. --}}
        @if ($annivPartenaire['infoDue'])
            {{-- Le scintillement reste toujours actif : il appelle l'utilisateur.
                 Seul change le passage au-dessus du voile, pour que la flèche
                 du modal désigne un bouton bien visible. --}}
            function annivBoutonEtat(modaleOuverte) {
                document.getElementById('hero-celebrer')?.classList.toggle('anniv-cible', modaleOuverte);
            }

            function annivInfoOuvrir() {
                document.getElementById('anniv-info').style.display = 'flex';
                annivBoutonEtat(true);
            }

            function annivInfoSuite() {
                document.getElementById('anniv-info').style.display = 'none';
                document.getElementById('anniv-tuto').style.display = 'flex';
                annivBoutonEtat(true);
            }

            {{-- Le POST n'a lieu qu'après le 2e modal : on est donc sûr que
                 l'utilisateur a vu l'explication *et* l'indicateur. --}}
            function annivTutoFermer() {
                const couche = document.getElementById('anniv-tuto');
                couche.style.display = 'none';
                fetch('{{ route('anniversaire.info.vue') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({}),
                }).then(() => {
                        // Le scintillement n'est pas touché ici : il reste.
                        annivBoutonEtat(false);
                    }).catch(() => {});
            }
        @endif

        {{-- Un seul modale à la fois : si la sensibilisation Octobre rose et le
             tutoriel d'anniversaire tombent le même jour, le premier ouvre,
             et le second attend la validation du premier. --}}
        document.addEventListener('DOMContentLoaded', function () {
            var suite = function () {
                @if ($octobreRose['due'])
                    octobreRoseOuvrir();
                @endif
                @if ($annivPartenaire['infoDue'] && ! $octobreRose['due'])
                    annivInfoOuvrir();
                @endif
            };

            // Si la popup « question du soir » de 20h est déjà là, on attend.
            if (window.djModalLibre) window.djModalLibre(suite);
            else suite();
        });
    </script>
@endpush

@push('scripts')
    <script>
        function setLigne(el, data, moi) {
            if (data.enLigne) {
                el.innerHTML = '<span style="color:var(--success)">● en ligne</span>';
            } else if (data.present) {
                el.innerHTML = '<span class="muted">En ligne il y\'a ' + data.heure + '</span>';
            } else {
                el.innerHTML = moi
                    ? '<span class="muted">Pas encore en ligne aujourd\'hui</span>'
                    : '<span class="muted">En attente de connexion…</span>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling('{{ route('couple.activite') }}', (data) => {
                setLigne(document.getElementById('ligne-moi'), data.moi, true);
                setLigne(document.getElementById('ligne-partenaire'), data.partenaire, false);
            }, { interval: 15000 });

            window.moodLottie?.mount(document);
        });
    </script>
@endpush