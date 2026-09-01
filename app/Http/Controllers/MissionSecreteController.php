<?php

namespace App\Http\Controllers;

use App\Models\MissionSecrete;
use App\Models\Point;
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

    public const DELAIS = [
        'facile' => 24,
        'moyen' => 48,
        'difficile' => 168,
    ];

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $me = Auth::user();
        $partner = $couple->partnerOf($me);

        $mesMissions = $couple->missionsSecrettes()->where('joueur_cible_id', $me->id)->orderByDesc('id')->get();
        $sesMissions = $couple->missionsSecrettes()->where('joueur_cible_id', $partner->id)->orderByDesc('id')->get();

        $ordre = ['en_attente' => 0, 'en_cours' => 1, 'accomplie' => 2, 'demasquee' => 3, 'echouee' => 4];

        return view('jeux.mission.index', [
            'couple' => $couple,
            'me' => $me,
            'partner' => $partner,
            'mesMissions' => $mesMissions->sortBy(fn ($m) => $ordre[$m->statut] ?? 5),
            'sesMissions' => $sesMissions->sortBy(fn ($m) => $ordre[$m->statut] ?? 5),
            'reponduAujourdhui' => $me->devin_mission_jour?->isToday() ?? false,
            'nombreReponses' => $me->devin_mission_jour?->isToday()
                ? (int) $me->devin_mission_compteur
                : 0,
            'derniereReponse' => $me->devin_mission_reponse,
            'resultatDevin' => $me->devin_mission_resultat,
        ]);
    }

    public function nouvelle(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;
        $partner = $couple->partnerOf($user);

        $frequence = (int) $request->input('frequence', 24);
        $frequence = in_array($frequence, [24, 48, 168], true) ? $frequence : 24;

        $derniere = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $user->id)
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();

        if ($derniere) {
            return response()->json(['error' => 'Attends un peu avant de tirer une nouvelle mission.'], 429);
        }

        $mission = self::MISSIONS[array_rand(self::MISSIONS)];
        $duree = self::DELAIS[$mission['difficulte']];

        $details = session('frequence_perso_'.$user->id);

        $created = MissionSecrete::create([
            'couple_id' => $couple->id,
            'joueur_cible_id' => $user->id,
            'texte' => $mission['texte'],
            'difficulte' => $mission['difficulte'],
            'statut' => 'en_attente',
            'date_debut' => now(),
            'date_fin' => now()->addHours($details && ($details['frequence'] ?? $frequence) ? $frequence : $duree),
        ]);

        session(['frequence_perso_'.$user->id => ['frequence' => $frequence, 'date' => now()]]);

        return response()->json(['ok' => true, 'id' => $created->id, 'message' => 'Mission secrète envoyée !']);
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

        $mission->forceFill(['statut' => 'en_cours', 'revele_at' => now()])->save();

        return response()->json(['ok' => true]);
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

        // Silence total : le/la partenaire ne sait pas que la mission est accomplie.
        $mission->forceFill(['statut' => 'accomplie', 'accomplie_at' => now(), 'revele_at' => now()])->save();

        return response()->json(['ok' => true, 'message' => 'Mission accomplie ! Le jeu du soir décidera si tu passes inaperçu·e.']);
    }

    public function questionDuSoir(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;

        $aujourdhui = today()->toDateString();
        $compteur = (int) $user->devin_mission_compteur;

        // Nouveau jour → on repart à zéro (5 réponses par jour).
        if ($user->devin_mission_jour?->toDateString() !== $aujourdhui) {
            $compteur = 0;
        }

        if ($compteur >= 5) {
            return response()->json(['error' => 'Tu as déjà répondu 5 fois à la question du soir aujourd\'hui.'], 422);
        }

        $data = $request->validate([
            'reponse' => ['required', 'in:oui,non'],
        ]);

        $partenaire = $couple->partnerOf($user);

        // Missions accomplies par le/la partenaire depuis la dernière réponse, pas encore devinées.
        $missions = $couple->missionsSecrettes()
            ->where('joueur_cible_id', $partenaire->id)
            ->where('statut', 'accomplie')
            ->whereNull('devine')
            ->when($user->devin_mission_jour, fn ($q, $jour) => $q->where('accomplie_at', '>', $jour->startOfDay()))
            ->orderBy('id')
            ->get();

        $nb = $missions->count();

        if ($request->input('reponse') === 'oui') {
            if ($nb > 0) {
                foreach ($missions as $mission) {
                    $mission->forceFill(['statut' => 'demasquee', 'devine' => 'mission'])->save();
                }
                Point::add($user, $couple, 10 * $nb, 'Mission secrète démasquée');
                Point::add($partenaire, $couple, 10 * $nb, 'Mission accomplie mais démasquée');
                $resultat = 'demasquee:'.$nb;
                $message = 'Bien vu ! '.$nb.' mission(s) secrète(s) démasquée(s). +'.(10 * $nb).' pts chacun.';
            } else {
                $resultat = 'fausse';
                $message = 'Fausse alerte ! Aucune mission n\'était en jeu. Tout était spontané.';
            }
        } else {
            if ($nb > 0) {
                foreach ($missions as $mission) {
                    $mission->forceFill(['devine' => 'spontane'])->save();
                }
                Point::add($partenaire, $couple, 25 * $nb, 'Mission accomplie sans être démasqué');
                $resultat = 'ratee:'.$nb;
                $message = 'Raté, c\'était '.$nb.' mission(s) ! '.$partenaire->name.' passe incognito (+'.(25 * $nb).' pts).';
            } else {
                $resultat = 'rien';
                $message = 'Rien à signaler. Ton/ta partenaire n\'a rien fait de suspect.';
            }
        }

        if ($nb > 0) {
            RecompenseService::check($couple);
        }

        $user->forceFill([
            'devin_mission_jour' => now(),
            'devin_mission_reponse' => $data['reponse'],
            'devin_mission_resultat' => $resultat,
            'devin_mission_compteur' => $compteur + 1,
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

    protected function authorize(MissionSecrete $mission): void
    {
        abort_if($mission->couple_id !== Auth::user()->couple_id, 403);
    }
}
