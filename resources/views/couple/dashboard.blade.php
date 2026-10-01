@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <div class="fadeIn">
        {{-- Couple header --}}
        <div class="hero-card">
            <div class="hero-orb hero-orb-rose"></div>
            <div class="hero-orb hero-orb-blue"></div>
            <div class="hero-inner">
                <div class="hero-pair">
                    <div class="hero-avatars">
                        <x-avatar :user="$me" class="lg hero-avatar-me" />
                        <span class="hero-heart">💞</span>
                        <x-avatar :user="$partner" class="lg hero-avatar-them" />
                    </div>
                    <div class="hero-names">{{ $me->name }} <span class="hero-amp">&</span> {{ $partner->name }}</div>
                    <div class="hero-sub tiny muted">Votre duo · votre histoire</div>
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
                </div>
            </div>

            @php $anivs = array_values(array_filter([$annivMoi, $annivPartenaire])); @endphp
            @if ($anivs)
                <div class="divider"></div>
                <div class="aniv-list">
                    @foreach ($anivs as $anniv)
                        @if ($anniv['jours'] === 0)
                            {{-- Jour J : une seule ligne à la place de la fiche.
                                 Le lien cadeau, s'il y en a un, est dans le hero. --}}
                            <div class="aniv-row aniv-row-jourj">
                                🎉 C'est l'anniversaire de {{ $anniv['name'] }} !
                            </div>
                        @else
                            <div class="aniv-row">
                                <div class="aniv-icon">🎂</div>
                                <div class="aniv-body grow">
                                    <div class="aniv-name">Anniversaire de {{ $anniv['name'] }}</div>
                                    <div class="tiny muted">
                                        @if ($anniv['date'])
                                            {{ $anniv['date']->translatedFormat('l j F Y') }}
                                        @else
                                            Date de naissance à renseigner sur le profil
                                        @endif
                                    </div>
                                </div>
                                <div class="aniv-actions">
                                    <div class="aniv-pill">
                                        @if ($anniv['jours'] === null)
                                            <span class="tiny muted">—</span>
                                        @else
                                            j-{{ $anniv['jours'] }} jours
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Météo du couple --}}
        <section class="section-head">
            <h2>Météo du couple</h2>
            <a href="{{ route('meteo.index') }}" class="tiny">partager la mienne →</a>
        </section>
        <div class="meteo-card">
            <div class="meteo-orb meteo-orb-1"></div>
            <div class="meteo-orb meteo-orb-2"></div>
            <div class="meteo-grid">
                <div class="meteo-cell{{ $meteoMoi ? '' : ' meteo-cell-empty' }}">
                    <div class="meteo-cell-top">
                        <x-avatar :user="$me" class="sm" />
                        <span class="meteo-cell-name">{{ $me->name }}</span>
                    </div>
                    <div class="meteo-cell-emoji">
                        @if ($meteoMoi)
                            <span class="mood-anim" data-lottie="{{ $meteoMoi['lottie'] }}">{{ $meteoMoi['emoji'] }}</span>
                        @else
                            ❓
                        @endif
                    </div>
                    <div class="meteo-cell-label tiny">
                        @if ($meteoMoi)
                            {{ $meteoMoi['label'] }}
                        @else
                            <span class="muted">Pas encore partagée</span>
                        @endif
                    </div>
                </div>
                <div class="meteo-cell{{ $meteoPartenaire ? '' : ' meteo-cell-empty' }}">
                    <div class="meteo-cell-top">
                        <x-avatar :user="$partner" class="sm" />
                        <span class="meteo-cell-name">{{ $partner->name }}</span>
                    </div>
                    <div class="meteo-cell-emoji">
                        @if ($meteoPartenaire)
                            <span class="mood-anim" data-lottie="{{ $meteoPartenaire['lottie'] }}">{{ $meteoPartenaire['emoji'] }}</span>
                        @else
                            ❓
                        @endif
                    </div>
                    <div class="meteo-cell-label tiny">
                        @if ($meteoPartenaire)
                            {{ $meteoPartenaire['label'] }}
                        @else
                            <span class="muted">Pas encore partagée</span>
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('meteo.index') }}" class="meteo-cta">
                <span class="meteo-cta-emoji">{{ $meteoSynthese['emoji'] ?? '🌥️' }}</span>
                <span class="meteo-cta-text grow">
                    {{ $meteoSynthese['label']
                        ?? ($meteoMoi || $meteoPartenaire ? "En attente de la météo de l'autre" : 'Partagez votre météo du jour') }}
                </span>
                <span class="meteo-cta-arrow">→</span>
            </a>
        </div>

        {{-- Les jeux --}}
        <section class="section-head"><h2>Les jeux</h2><span class="tiny muted">Joue à tour de rôle</span></section>
        <div class="game-grid">
            <a href="{{ route('discussion.index') }}" class="game-tile tile-discussion fadeIn" style="animation-delay:.01s">
                <div class="t-ico">💬</div>
                <div class="t-name">Discussion</div>
                <div class="t-desc">En privé, à deux</div>
            </a>
            <a href="{{ route('vo.index') }}" class="game-tile tile-vo fadeIn" style="animation-delay:.02s">
                <div class="t-ico">🎭</div>
                <div class="t-name">Vérité ou Action</div>
                <div class="t-desc">Doux, chaud ou brûlant</div>
            </a>
            <a href="{{ route('ouinon.index') }}" class="game-tile tile-ouinon fadeIn" style="animation-delay:.06s">
                <div class="t-ico">⚖️</div>
                <div class="t-name">Oui ou Non</div>
                <div class="t-desc">10 questions test</div>
            </a>
            <a href="{{ route('mission.index') }}" class="game-tile tile-mission fadeIn" style="animation-delay:.10s">
                <div class="t-ico">🕵️</div>
                <div class="t-name">Mission secrète</div>
                <div class="t-desc">Il/elle ne saura jamais</div>
            </a>
            <a href="{{ route('enveloppe.index') }}" class="game-tile tile-enveloppe fadeIn" style="animation-delay:.14s">
                <div class="t-ico">💌</div>
                <div class="t-name">Enveloppes</div>
                <div class="t-desc">Rouge, bleue, verte</div>
            </a>
            <a href="{{ route('quiz.index') }}" class="game-tile tile-quiz fadeIn" style="animation-delay:.18s">
                <div class="t-ico">❓</div>
                <div class="t-name">Tu me connais ?</div>
                <div class="t-desc">Réponds à ma place</div>
            </a>
            <a href="{{ route('qdn2.index') }}" class="game-tile tile-qui-nous-deux fadeIn" style="animation-delay:.20s">
                <div class="t-ico">🙋</div>
                <div class="t-name">Qui de nous deux ?</div>
                <div class="t-desc">Accord → +5 pts</div>
            </a>
            <a href="{{ route('question.index') }}" class="game-tile tile-question fadeIn" style="animation-delay:.22s">
                <div class="t-ico">🌅</div>
                <div class="t-name">Question du jour</div>
                <div class="t-desc">Une par jour, ensemble</div>
            </a>
            <a href="{{ route('meteo.index') }}" class="game-tile tile-meteo fadeIn" style="animation-delay:.26s">
                <div class="t-ico">🌦️</div>
                <div class="t-name">Météo du couple</div>
                <div class="t-desc">Ton baromètre à deux</div>
            </a>
            <a href="{{ route('mots-croises.index') }}" class="game-tile tile-mots-croises fadeIn" style="animation-delay:.30s">
                <div class="t-ico">🧩</div>
                <div class="t-name">Mots croisés</div>
                <div class="t-desc">Une grille, à deux</div>
            </a>
            <a href="{{ route('bucket-list.index') }}" class="game-tile tile-bucket-list fadeIn" style="animation-delay:.34s">
                <div class="t-ico">🧳</div>
                <div class="t-name">Bucket List</div>
                <div class="t-desc">Vos projets, vos souvenirs</div>
            </a>
            <a href="{{ route('calendrier.index') }}" class="game-tile tile-calendrier fadeIn" style="animation-delay:.38s">
                <div class="t-ico">🗓️</div>
                <div class="t-name">Calendrier</div>
                <div class="t-desc">Votre journée côte à côte</div>
            </a>
            <a href="{{ route('ludo.index') }}" class="game-tile tile-ludo fadeIn" style="animation-delay:.42s">
                <div class="t-ico">🎲</div>
                <div class="t-name">Ludo à deux</div>
                <div class="t-desc">La course des pions</div>
            </a>
            <a href="{{ route('quoridor.index') }}" class="game-tile tile-quoridor fadeIn" style="animation-delay:.46s">
                <div class="t-ico">🧱</div>
                <div class="t-name">Quoridor</div>
                <div class="t-desc">Barre la route, gagne</div>
            </a>
        </div>

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