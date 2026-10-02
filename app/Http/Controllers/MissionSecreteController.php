<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionSecrete;
use App\Models\MissionTrack;
use App\Models\Point;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MissionSecreteController extends Controller
{
    /**
     * Date à laquelle le catalogue remplace le tirage au hasard.
     *
     * Repli utilisé si la configuration est absente — par exemple un cache de
     * configuration périmé en prod. Sans ce repli, une valeur nulle ferait
     * interpreted « maintenant » comme date de bascule et bloquerait le jeu
     * pour tout le monde.
     */
    public const CATALOGUE_START_DATE_DEFAUT = '2026-10-03';

    /**
     * Récupère (ou crée) la mission du jour d'un partenaire.
     *
     * La mission provient du catalogue persistant `missions` et jamais deux fois
     * pour un même joueur : `mission_tracks` mémorise ce qui a déjà été servi,
     * donc on ne tire que parmi les missions non encore vues. Un joueur ayant
     * épuisé les 100 missions n'en obtient plus.
     *
     * @return array{0: ?MissionSecrete, 1: bool} la mission et « créée maintenant ? »
     */
    public static function genererPourUser($couple, User $user): array
    {
        $date = $user->localToday()->toDateString();
        $trouvee = fn () => $couple->missionsSecrettes()
            ->where('joueur_cible_id', $user->id)
            ->whereDate('date_mission', $date)
            ->first();

        $mission = $trouvee();
        if ($mission || $user->deadlineSoirPassee()) {
            return [$mission, false];
        }

        if (! self::catalogueActif($user)) {
            return [null, false];
        }

        try {
            // La transaction retourne false si le catalogue est épuisé : rien
            // n'est créé et rien n'est consommé.
            $servie = DB::transaction(function () use ($couple, $user, $date) {
                // L'ordre par id rend la distribution déterministe : on sert les
                // missions dans l'ordre du catalogue, ce qui garantit qu'aucune ne
                // revient avant d'avoir parcouru les 100.
                $catalogue = Mission::query()
                    ->whereDoesntHave('tracks', fn ($q) => $q->where('user_id', $user->id))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $catalogue) {
                    return false;
                }

                // L'unicité (user_id, mission_id) est la garde-fou finale : si
                // deux exécutions se chevauchent, la seconde insertion échoue et
                // toute la transaction est annulée plutôt que de servir un doublon.
                MissionTrack::create([
                    'user_id' => $user->id,
                    'mission_id' => $catalogue->id,
                    'served_at' => now(),
                ]);

                MissionSecrete::create([
                    'couple_id' => $couple->id,
                    'joueur_cible_id' => $user->id,
                    'texte' => $catalogue->texte,
                    'difficulte' => $catalogue->difficulte,
                    'statut' => 'en_attente',
                    'date_mission' => $date,
                    'date_debut' => now(),
                    'date_fin' => $user->deadlineSoir()->setTimezone(config('app.timezone', 'UTC')),
                ]);

                return true;
            });
        } catch (\Throwable) {
            return [$trouvee(), false];
        }

        return [$trouvee(), $servie];
    }

    /**
     * Le catalogue ne devient actif qu'à la date configurée, pour que les
     * missions déjà attribuées le jour même restent valables.
     */
    public static function catalogueActif(User $user): bool
    {
        return $user->localToday()->gte(Carbon::parse(
            config('missions.catalogue_start_date') ?: self::CATALOGUE_START_DATE_DEFAUT
        ));
    }

    /**
     * Nombre de missions du catalogue que ce joueur n'a pas encore reçues.
     */
    public static function missionsRestantes(User $user): int
    {
        return Mission::query()
            ->whereDoesntHave('tracks', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $me = Auth::user();
        $partner = $couple->partnerOf($me)->fresh();

        $this->finaliserMissions($couple, $me);
        $this->finaliserMissions($couple, $partner);

        self::genererPourUser($couple, $me);

        $today = $me->localToday()->toDateString();
        $partnerToday = $partner->localToday()->toDateString();

        $maMission = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $me->id)
            ->whereDate('date_mission', $today)
            ->first();

        $saMission = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $partner->id)
            ->whereDate('date_mission', $partnerToday)
            ->first();

        $questionOuverte = $me->deadlineSoirPassee();

        // Une seule requête : le compteur sert aussi à détecter l'épuisement.
        $catalogueActif = self::catalogueActif($me);
        $missionsRestantes = $catalogueActif ? self::missionsRestantes($me) : null;

        return view('jeux.mission.index', [
            'couple' => $couple,
            'me' => $me,
            'partner' => $partner,
            'maMission' => $maMission,
            'saMission' => $saMission,
            'catalogueEpuise' => $catalogueActif && $missionsRestantes === 0,
            'missionsRestantes' => $missionsRestantes,
            'questionOuverte' => $questionOuverte,
            'reponduAujourdhui' => $me->devin_mission_jour?->toDateString() === $today,
            'nombreReponses' => $me->devin_mission_jour?->toDateString() === $today ? 1 : 0,
            'derniereReponse' => $me->devin_mission_reponse,
            'resultatDevin' => $me->devin_mission_resultat,
            'partenaireARepondu' => $partner->devin_mission_jour?->toDateString() === $partnerToday,
            'partenaireReponse' => $partner->devin_mission_reponse,
            'partenaireResultat' => $partner->devin_mission_resultat,
        ]);
    }

    public function reveler(Request $request, MissionSecrete $mission): JsonResponse
    {
        $this->authorize($mission);

        if ($mission->joueur_cible_id !== $request->user()->id) {
            return response()->json(['error' => 'Ce n\'est pas ta mission.'], 403);
        }

        if ($mission->statut !== 'en_attente') {
            return response()->json(['error' => 'Mission déjà révélée.'], 422);
        }

        if ($request->user()->deadlineSoirPassee()) {
            return response()->json(['error' => 'La mission du jour est terminée.'], 422);
        }

        $mission->forceFill([
            'statut' => 'en_cours',
            'revele_at' => now(),
            'vue_par_cible' => true,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function refuser(Request $request, MissionSecrete $mission): JsonResponse
    {
        $this->authorize($mission);

        if ($mission->joueur_cible_id !== $request->user()->id) {
            return response()->json(['error' => 'Ce n\'est pas ta mission.'], 403);
        }

        if ($mission->statut !== 'en_attente') {
            return response()->json(['error' => 'Mission déjà traitée.'], 422);
        }

        $mission->forceFill([
            'statut' => 'refusee',
            'vue_par_cible' => true,
        ])->save();

        return response()->json(['ok' => true, 'message' => 'Mission refusée.']);
    }

    public function accomplir(Request $request, MissionSecrete $mission): JsonResponse
    {
        $this->authorize($mission);

        if ($mission->joueur_cible_id !== $request->user()->id) {
            return response()->json(['error' => 'Ce n\'est pas ta mission.'], 403);
        }

        if (! in_array($mission->statut, ['en_cours', 'en_attente'], true)) {
            return response()->json(['error' => 'Mission déjà traitée.'], 422);
        }

        $mission->forceFill([
            'statut' => 'accomplie',
            'accomplie_at' => now(),
            'revele_at' => now(),
            'vue_par_cible' => true,
        ])->save();

        return response()->json(['ok' => true, 'message' => 'Mission accomplie ! Le jeu du soir décidera si tu passes inaperçu·e.']);
    }

    public function questionDuSoir(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;

        if (! $user->deadlineSoirPassee()) {
            return response()->json(['error' => 'La question du soir arrive à 20h.'], 422);
        }

        $today = $user->localToday()->toDateString();

        if ($user->devin_mission_jour?->toDateString() === $today) {
            return response()->json(['error' => 'Tu as déjà répondu à la question du soir aujourd\'hui.'], 422);
        }

        $data = $request->validate([
            'reponse' => ['required', 'in:oui,non'],
        ]);

        $partenaire = $couple->partnerOf($user);
        $partnerToday = $partenaire->localToday()->toDateString();

        $mission = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $partenaire->id)
            ->whereDate('date_mission', $partnerToday)
            ->first();

        if ($mission && in_array($mission->statut, ['en_attente', 'en_cours']) && $partenaire->deadlineSoirPassee()) {
            $mission->forceFill(['statut' => 'echouee'])->save();
        }

        $accomplie = $mission && $mission->statut === 'accomplie' && is_null($mission->devine);

        if ($request->input('reponse') === 'oui') {
            if ($accomplie) {
                $mission->forceFill(['statut' => 'demasquee', 'devine' => 'mission'])->save();
                Point::add($user, $couple, 10, 'Mission secrète démasquée');
                Point::add($partenaire, $couple, 10, 'Mission accomplie mais démasquée');
                $resultat = 'demasquee:1';
                $message = 'Bien vu ! Mission secrète démasquée. +10 pts chacun.';
            } else {
                $resultat = 'fausse';
                $message = 'Fausse alerte ! Aucune mission n\'était en jeu. Tout était spontané.';
            }
        } else {
            if ($accomplie) {
                $mission->forceFill(['devine' => 'spontane'])->save();
                Point::add($partenaire, $couple, 25, 'Mission accomplie sans être démasqué');
                $resultat = 'ratee:1';
                $message = 'Raté, c\'était une mission ! '.$partenaire->name.' passe incognito (+25 pts).';
            } else {
                $resultat = 'rien';
                $message = 'Rien à signaler. Ton/ta partenaire n\'a rien fait de suspect.';
            }
        }

        if ($accomplie) {
            RecompenseService::check($couple);
        }

        $user->forceFill([
            'devin_mission_jour' => now(),
            'devin_mission_reponse' => $data['reponse'],
            'devin_mission_resultat' => $resultat,
            'devin_mission_compteur' => 1,
        ])->save();

        return response()->json(['ok' => true, 'message' => $message]);
    }

    public function echouer(Request $request, MissionSecrete $mission): JsonResponse
    {
        $this->authorize($mission);

        if ($mission->joueur_cible_id !== $request->user()->id) {
            return response()->json(['error' => 'Ce n\'est pas ta mission.'], 403);
        }

        if ($mission->statut !== 'en_cours') {
            return response()->json(['error' => 'Mission déjà traitée.'], 422);
        }

        $mission->forceFill(['statut' => 'echouee'])->save();

        return response()->json(['ok' => true, 'message' => 'Mission abandonnée.']);
    }

    public function marquerVu(Request $request, MissionSecrete $mission): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;
        $partner = $couple->partnerOf($user);

        if ($mission->couple_id !== $couple->id) {
            return response()->json(['error' => 'Accès interdit.'], 403);
        }

        $data = $request->validate([
            'role' => ['required', 'in:cible,partenaire'],
        ]);

        if ($data['role'] === 'cible' && $mission->joueur_cible_id !== $user->id) {
            return response()->json(['error' => 'Accès interdit.'], 403);
        }

        if ($data['role'] === 'partenaire' && $mission->joueur_cible_id !== $partner?->id) {
            return response()->json(['error' => 'Accès interdit.'], 403);
        }

        $column = $data['role'] === 'cible' ? 'vue_par_cible' : 'vue_par_partenaire';
        $mission->forceFill([$column => true])->save();

        return response()->json(['ok' => true]);
    }

    public function infos(): JsonResponse
    {
        $me = Auth::user();
        $couple = $me->coupleModel;
        $partner = $couple->partnerOf($me)->fresh();

        $today = $me->localToday()->toDateString();
        $partnerToday = $partner->localToday()->toDateString();

        self::genererPourUser($couple, $me);

        $this->finaliserMissions($couple, $me);

        $maMission = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $me->id)
            ->whereDate('date_mission', $today)
            ->first();

        $modals = [];

        if ($maMission && $maMission->statut === 'en_attente' && ! $maMission->vue_par_cible && ! $me->deadlineSoirPassee()) {
            $modals[] = [
                'type' => 'mission',
                'mission_id' => $maMission->id,
                'title' => '🕵️ Nouvelle mission disponible',
                'message' => 'Une nouvelle mission est disponible. Va sur la page Mission secrète pour la découvrir.',
            ];
        }

        if ($me->deadlineSoirPassee()) {
            $saMission = $couple->missionsSecrettes()
                ->where('joueur_cible_id', $partner->id)
                ->whereDate('date_mission', $partnerToday)
                ->first();

            if ($saMission && ! $saMission->vue_par_partenaire) {
                $modals[] = [
                    'type' => 'question',
                    'mission_id' => $saMission->id,
                    'title' => '🌙 Question du soir',
                    'message' => 'Il est 20h. Viens répondre à la question du soir sur la page Mission secrète.',
                ];
            }

            $partenaireARepondu = $partner->devin_mission_jour?->toDateString() === $partnerToday;

            if ($partenaireARepondu && $me->devin_verdict_vu_jour?->toDateString() !== $today) {
                $modals[] = $this->modalVerdict($partner, $maMission);
            }
        }

        return response()->json(['modals' => $modals]);
    }

    public function marquerVerdictVu(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['devin_verdict_vu_jour' => $user->localNow()])->save();

        return response()->json(['ok' => true]);
    }

    protected function modalVerdict(User $partner, ?MissionSecrete $maMission): array
    {
        $nom = $partner->name;
        $reponse = $partner->devin_mission_reponse === 'oui' ? 'Oui, je le/la soupçonne' : 'Non, tout était spontané';
        $actions = $maMission?->vue_par_partenaire
            ? 'a vu la question du soir et a répondu'
            : 'a répondu';

        $message = match (true) {
            $maMission && $maMission->statut === 'demasquee' => "{$nom} {$actions} : « {$reponse} » — elle/il t'a démasqué·e, +10 pts chacun.",
            $maMission && $maMission->statut === 'accomplie' && $maMission->devine === 'spontane' => "{$nom} {$actions} : « {$reponse} » — tu passes incognito, +25 pts.",
            $partner->devin_mission_reponse === 'oui' => "{$nom} {$actions} : « {$reponse} » — fausse alerte, aucun point.",
            default => "{$nom} {$actions} : « {$reponse} » — rien à signaler, aucun point.",
        };

        return [
            'type' => 'verdict',
            'title' => '🏆 Verdict du soir',
            'message' => $message,
        ];
    }

    protected function finaliserMissions($couple, $user): void
    {
        if (! $user) {
            return;
        }

        $today = $user->localToday()->toDateString();

        $couple->missionsSecrettes()
            ->where('joueur_cible_id', $user->id)
            ->whereIn('statut', ['en_attente', 'en_cours'])
            ->whereDate('date_mission', '<=', $today)
            ->where('date_fin', '<', now())
            ->update(['statut' => 'echouee']);
    }

    protected function authorize(MissionSecrete $mission): void
    {
        abort_if($mission->couple_id !== Auth::user()->couple_id, 403);
    }
}
