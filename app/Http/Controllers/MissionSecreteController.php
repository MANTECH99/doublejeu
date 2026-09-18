<?php

namespace App\Http\Controllers;

use App\Models\MissionSecrete;
use App\Models\Point;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MissionSecreteController extends Controller
{
    public const MISSIONS = [
        ['texte' => 'Envoie un message disant « Tu me manques » à un moment inattendu aujourd\'hui', 'difficulte' => 'facile'],
        ['texte' => 'Appelle ton/ta partenaire pour lui dire « Je t\'aime » sans raison apparente', 'difficulte' => 'facile'],
        ['texte' => 'Envoie un selfie en faisant un clin d\'œil', 'difficulte' => 'facile'],
        ['texte' => 'Écris un poème court et envoie-le par message', 'difficulte' => 'moyen'],
        ['texte' => 'Envoie un vocal de 20 secondes en chuchotant', 'difficulte' => 'moyen'],
        ['texte' => 'Demande à ton/ta partenaire quel est son rêve le plus fou pour vous deux', 'difficulte' => 'facile'],
        ['texte' => 'Envoie un message coquin à 14h exactement', 'difficulte' => 'difficile'],
        ['texte' => 'Fais un compliment très précis sur une partie du corps de ton/ta partenaire', 'difficulte' => 'facile'],
        ['texte' => 'Envoie une photo de l\'endroit où tu aimerais qu\'on se retrouve', 'difficulte' => 'moyen'],
        ['texte' => 'Raconte à ton/ta partenaire un souvenir précis de votre première rencontre', 'difficulte' => 'facile'],
        ['texte' => 'Envoie un emoji mystérieux et attends sa réaction', 'difficulte' => 'facile'],
        ['texte' => 'Pose une question très intime à ton/ta partenaire', 'difficulte' => 'difficile'],
        ['texte' => 'Envoie une photo de ce que tu portes en ce moment', 'difficulte' => 'moyen'],
        ['texte' => 'Dis à ton/ta partenaire de regarder la lune à la même heure ce soir', 'difficulte' => 'facile'],
        ['texte' => 'Envoie un message en langue étrangère et laisse-le/la deviner', 'difficulte' => 'moyen'],
        ['texte' => 'Mets une photo de vous deux en fond d\'écran sans le mentionner', 'difficulte' => 'moyen'],
        ['texte' => 'Raconte ton meilleur souvenir de nuit avec lui/elle en vocal', 'difficulte' => 'difficile'],
        ['texte' => 'Envoie un audio de toi en train de rire aux éclats', 'difficulte' => 'facile'],
    ];

    /**
     * Récupère (ou crée) la mission du jour d'un partenaire.
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

        $mission = self::MISSIONS[array_rand(self::MISSIONS)];

        try {
            MissionSecrete::create([
                'couple_id' => $couple->id,
                'joueur_cible_id' => $user->id,
                'texte' => $mission['texte'],
                'difficulte' => $mission['difficulte'],
                'statut' => 'en_attente',
                'date_mission' => $date,
                'date_debut' => now(),
                'date_fin' => $user->deadlineSoir()->setTimezone(config('app.timezone', 'UTC')),
            ]);
        } catch (\Throwable) {
            return [$trouvee(), false];
        }

        return [$trouvee(), true];
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

        return view('jeux.mission.index', [
            'couple' => $couple,
            'me' => $me,
            'partner' => $partner,
            'maMission' => $maMission,
            'saMission' => $saMission,
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
