@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <div class="fadeIn">
        {{-- Couple header --}}
        <div class="card" style="background:linear-gradient(150deg, rgba(230,57,70,.14), rgba(255,107,107,.05)), var(--card)">
            <div class="flex between items-center">
                <div class="flex items-center gap12">
                    <x-avatar :user="$me" class="lg" />
                    <div>
                        <div style="font-weight:700; font-size:17px">{{ $me->name }}</div>
                        <div class="tiny muted">💞 avec {{ $partner->name }}</div>
                    </div>
                </div>
                <x-avatar :user="$partner" class="lg" style="; border:2px solid rgba(255,255,255,.2)" />
            </div>

            <div class="divider"></div>

            @foreach (array_filter([$annivMoi, $annivPartenaire]) as $anniv)
                <div class="flex between items-center" style="padding:6px 2px">
                    <div class="grow">
                        <div style="font-size:13px;font-weight:600">🎂 Anniversaire de {{ $anniv['name'] }}</div>
                        <div class="tiny muted">
                            @if ($anniv['date'])
                                {{ $anniv['date']->translatedFormat('l j F Y') }}
                            @else
                                Date de naissance à renseigner sur le profil
                            @endif
                        </div>
                    </div>
                    <div style="font-weight:700;font-size:14px">
                        @if ($anniv['jours'] === null)
                            <span class="tiny muted">—</span>
                        @elseif ($anniv['jours'] > 0)
                            j-{{ $anniv['jours'] }} jours
                        @else
                            🎉 C'est aujourd'hui !
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Météo du couple --}}
        <section class="section-head">
            <h2>Météo du couple</h2>
            <a href="{{ route('meteo.index') }}" class="tiny">partager la mienne →</a>
        </section>
        <div class="card" style="background:linear-gradient(150deg, rgba(93,173,226,.16), rgba(52,152,219,.06)), var(--card)">
            <div class="grid2">
                <div class="center" style="padding:14px 8px">
                    <div class="tiny muted">{{ $me->name }}</div>
                    <div style="font-size:40px;line-height:1.1">{{ $meteoMoi['emoji'] ?? '❓' }}</div>
                    <div class="tiny">
                        @if ($meteoMoi)
                            {{ $meteoMoi['label'] }}
                        @else
                            <span class="muted">Pas encore partagée</span>
                        @endif
                    </div>
                </div>
                <div class="center" style="padding:14px 8px">
                    <div class="tiny muted">{{ $partner->name }}</div>
                    <div style="font-size:40px;line-height:1.1">{{ $meteoPartenaire['emoji'] ?? '❓' }}</div>
                    <div class="tiny">
                        @if ($meteoPartenaire)
                            {{ $meteoPartenaire['label'] }}
                        @else
                            <span class="muted">Pas encore partagée</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <a href="{{ route('meteo.index') }}" class="flex between items-center" style="padding:6px 2px">
                <span style="font-size:22px">{{ $meteoSynthese['emoji'] ?? '🌥️' }}</span>
                <span class="grow" style="font-weight:600;padding:0 10px">
                    {{ $meteoSynthese['label']
                        ?? ($meteoMoi || $meteoPartenaire ? "En attente de la météo de l'autre" : 'Partagez votre météo du jour') }}
                </span>
                <span class="tiny muted">→</span>
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
                            <span class="muted">Actif·ve il y a {{ $me->last_active_at->diffForHumans() }}</span>
                        @else
                            <span class="muted">Pas encore actif·ve aujourd'hui</span>
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
                            <span class="muted">Actif·ve il y a {{ $partner->last_active_at->diffForHumans() }}</span>
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
                        <div class="tiny muted">Tire une mission secrète pour surprendre</div>
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
@endsection

@push('scripts')
    <script>
        function setLigne(el, data, moi) {
            if (data.enLigne) {
                el.innerHTML = '<span style="color:var(--success)">● en ligne</span>';
            } else if (data.present) {
                el.innerHTML = '<span class="muted">Actif·ve il y a ' + data.heure + '</span>';
            } else {
                el.innerHTML = moi
                    ? '<span class="muted">Pas encore actif·ve aujourd\'hui</span>'
                    : '<span class="muted">En attente de connexion…</span>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            startPolling('{{ route('couple.activite') }}', (data) => {
                setLigne(document.getElementById('ligne-moi'), data.moi, true);
                setLigne(document.getElementById('ligne-partenaire'), data.partenaire, false);
            }, { interval: 15000 });
        });
    </script>
@endpush