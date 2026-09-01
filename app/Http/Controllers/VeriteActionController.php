<?php

namespace App\Http\Controllers;

use App\Models\CarteAction;
use App\Models\CarteVerite;
use App\Models\Gage;
use App\Models\PartieVO;
use App\Models\Point;
use App\Models\TourVO;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\PushService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VeriteActionController extends Controller
{
    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $partie = PartieVO::where('couple_id', $couple->id)
            ->where('status', 'en_cours')
            ->latest()
            ->first();

        return view('jeux.vo.index', [
            'couple' => $couple,
            'partie' => $partie,
            'historique' => PartieVO::where('couple_id', $couple->id)
                ->where('status', '!=', 'en_cours')
                ->with('tours')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'niveau' => ['required', 'in:doux,chaud,brulant'],
        ]);

        $couple = $request->user()->coupleModel;

        PartieVO::where('couple_id', $couple->id)
            ->where('status', 'en_cours')
            ->update(['status' => 'abandonnee']);

        $partie = PartieVO::create([
            'couple_id' => $couple->id,
            'niveau' => $data['niveau'],
            'status' => 'en_cours',
            'joueur_actif_id' => rand(0, 1) ? $couple->user1_id : $couple->user2_id,
            'score_joueur1' => 0,
            'score_joueur2' => 0,
        ]);

        ActivityService::touch($request->user());

        return redirect()->route('vo.jouer', $partie);
    }

    public function play(PartieVO $partie): View
    {
        $this->authorizeCouple($partie);

        ActivityService::touch(Auth::user());

        return view('jeux.vo.jouer', [
            'partie' => $partie,
            'couple' => $partie->couple,
        ]);
    }

    public function state(PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $user = Auth::user();
        $couple = $partie->couple;
        $partner = $couple->partnerOf($user);

        $lastTour = $partie->tours()->latest('id')->first();
        $dernierValide = $partie->tours()
            ->whereIn('statut', ['valide', 'refuse'])
            ->latest('id')
            ->first();

        $current = $partie->tours()
            ->whereIn('statut', ['en_attente', 'realise'])
            ->orderByDesc('id')
            ->first();

        $estActif = $partie->joueur_actif_id === $user->id;

        $stage = 'attente';
        if ($partie->status !== 'en_cours') {
            $stage = 'terminee';
        } elseif ($current && $current->statut === 'en_attente' && $estActif) {
            $stage = 'carte';
        } elseif ($current && $current->statut === 'realise' && ! $estActif) {
            $stage = 'validation';
        } elseif (! $current && $estActif) {
            $stage = 'choix';
        }

        $carte = null;
        if ($current && $estActif && $current->statut === 'en_attente') {
            $carte = [
                'id' => $current->id,
                'type' => $current->type,
                'texte' => $current->carteTexte(),
                'reponse' => $current->reponse,
            ];
        } elseif ($current && ! $estActif && $current->statut === 'realise') {
            $carte = [
                'id' => $current->id,
                'type' => $current->type,
                'texte' => $current->carteTexte(),
                'reponse' => $current->reponse,
            ];
        }

        return response()->json([
            'status' => $partie->status,
            'stage' => $stage,
            'estActif' => $estActif,
            'joueurActif' => [
                'id' => $partie->joueur_actif_id,
                'name' => $partie->joueurActif?->name,
            ],
            'mapartenaire' => $partner?->name,
            'scores' => $this->scores($partie),
            'carte' => $carte,
            'dernierTour' => $dernierValide ? [
                'type' => $dernierValide->type,
                'texte' => $dernierValide->carteTexte(),
                'joueur' => $dernierValide->joueur->name,
                'accepte' => $dernierValide->accepte,
                'points' => $dernierValide->points_gagnes,
                'statut' => $dernierValide->statut,
            ] : null,
            'dernierEvenement' => $lastTour ? [
                'id' => $lastTour->id,
                'joueur' => $lastTour->joueur->name,
                'texte' => $lastTour->carteTexte(),
                'statut' => $lastTour->statut,
                'type' => $lastTour->type,
            ] : null,
        ]);
    }

    public function choisis(Request $request, PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);
        $this->authorizeActif($partie);

        $data = $request->validate([
            'type' => ['required', 'in:verite,action'],
        ]);

        if ($partie->tours()->whereIn('statut', ['en_attente', 'realise'])->exists()) {
            return response()->json(['error' => 'Un tour est déjà en cours.'], 422);
        }

        if ($data['type'] === 'verite') {
            $carte = CarteVerite::where('niveau', $partie->niveau)
                ->inRandomOrder()->first();
            $tour = TourVO::create([
                'partie_id' => $partie->id,
                'joueur_id' => $partie->joueur_actif_id,
                'type' => 'verite',
                'carte_id' => $carte?->id,
                'statut' => 'en_attente',
            ]);
        } else {
            $carte = CarteAction::where('niveau', $partie->niveau)
                ->inRandomOrder()->first();
            $tour = TourVO::create([
                'partie_id' => $partie->id,
                'joueur_id' => $partie->joueur_actif_id,
                'type' => 'action',
                'carte_id' => $carte?->id,
                'statut' => 'en_attente',
            ]);
        }

        return response()->json([
            'ok' => true,
            'tour_id' => $tour->id,
            'texte' => $tour->carteTexte(),
            'type' => $tour->type,
        ]);
    }

    public function repond(Request $request, PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);
        $this->authorizeActif($partie);

        $data = $request->validate([
            'accepte' => ['required', 'boolean'],
            'reponse' => ['nullable', 'string', 'max:2000'],
        ]);

        $tour = $partie->tours()->whereIn('statut', ['en_attente', 'realise'])->latest('id')->first();
        if (! $tour || $tour->statut !== 'en_attente') {
            return response()->json(['error' => 'Aucun défi à accepter.'], 422);
        }

        $user = $request->user();
        $couple = $partie->couple;
        $partner = $couple->partnerOf($user);

        if ($data['accepte']) {
            $tour->forceFill([
                'reponse' => $data['reponse'] ?? null,
                'statut' => 'realise',
                'accepte' => true,
            ])->save();

            app(PushService::class)->sendToUser($partner, [
                'title' => '🎯 Défi réalisé !',
                'body' => $user->name.' a terminé son défi. Valide-le !',
                'url' => route('vo.jouer', $partie),
            ]);

            return response()->json(['ok' => true, 'statut' => 'realise', 'message' => 'Défi accepté ! Ton/ta partenaire doit maintenant valider.']);
        }

        // Refus : -5 à toi, +5 au partenaire, tirage d'un gage
        $tour->forceFill(['statut' => 'refuse', 'accepte' => false, 'points_gagnes' => -5])->save();

        $this->addScore($partie, $user, -5, 'Refus d\'un défi (Vérité ou Action)');
        $this->addScore($partie, $partner, 5, 'Défi refusé par le partenaire (Vérité ou Action)');

        $gage = Gage::inRandomOrder()->first();
        $tage = TourVO::create([
            'partie_id' => $partie->id,
            'joueur_id' => $user->id,
            'type' => 'gage',
            'carte_id' => $gage?->id,
            'statut' => 'refuse',
            'accepte' => false,
            'points_gagnes' => 0,
        ]);

        $this->switchTurn($partie);

        RecompenseService::check($couple);

        app(PushService::class)->sendToUser($partner, [
            'title' => '😅 Gage !',
            'body' => $user->name.' a refusé le défi. Un gage a été tiré et c\'est à toi de jouer !',
            'url' => route('vo.jouer', $partie),
        ]);

        return response()->json([
            'ok' => true,
            'statut' => 'refuse',
            'gage' => $tage->carteTexte(),
            'message' => 'Tu as refusé ! Voici ton gage.',
        ]);
    }

    public function valider(Request $request, PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $user = $request->user();

        if ($partie->joueur_actif_id === $user->id) {
            return response()->json(['error' => 'C\'est ton défi, attends que ton/ta partenaire valide.'], 422);
        }

        $tour = $partie->tours()->whereIn('statut', ['realise'])->latest('id')->first();
        if (! $tour) {
            return response()->json(['error' => 'Aucun défi à valider.'], 422);
        }

        $points = $tour->type === 'verite' ? 10 : 20;
        $tour->forceFill(['statut' => 'valide', 'points_gagnes' => $points])->save();

        $couple = $partie->couple;
        $this->addScore($partie, $tour->joueur, $points, 'Défi '.($tour->type === 'verite' ? 'Vérité' : 'Action').' accepté');

        $this->switchTurn($partie);

        RecompenseService::check($couple);

        app(PushService::class)->sendToUser($tour->joueur, [
            'title' => '✅ Défi validé !',
            'body' => $user->name.' a validé ton défi. Tu gagnes '.$points.' points !',
            'url' => route('vo.jouer', $partie),
        ]);

        return response()->json(['ok' => true, 'points' => $points, 'message' => 'Défi validé, +'.$points.' points pour '.$tour->joueur->name.' !']);
    }

    public function invalider(Request $request, PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $user = $request->user();

        if ($partie->joueur_actif_id === $user->id) {
            return response()->json(['error' => 'C\'est ton défi, attends le jugement de ton/ta partenaire.'], 422);
        }

        $tour = $partie->tours()->whereIn('statut', ['realise'])->latest('id')->first();
        if (! $tour) {
            return response()->json(['error' => 'Aucun défi à juger.'], 422);
        }

        $tour->forceFill(['statut' => 'refuse', 'accepte' => false, 'points_gagnes' => 0])->save();

        $this->switchTurn($partie);

        app(PushService::class)->sendToUser($tour->joueur, [
            'title' => '😬 Vérité invalidée',
            'body' => $user->name.' n\'a pas cru ta vérité. C\'est à nouveau ton tour !',
            'url' => route('vo.jouer', $partie),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Vérité invalidée, aucun point. C\'est maintenant au tour de '.$tour->joueur->name.'.',
        ]);
    }

    public function terminer(Request $request, PartieVO $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        if ($partie->status !== 'en_cours') {
            return response()->json(['error' => 'Partie déjà terminée.'], 422);
        }

        $couple = $partie->couple;
        $partie->status = 'terminee';

        $user = $request->user();
        $partner = $couple->partnerOf($user);

        $partie->save();

        app(PushService::class)->sendToUser($partner, [
            'title' => '🏁 Partie terminée !',
            'body' => $user->name.' a terminé la partie. Récompenses ?',
            'url' => route('vo.index'),
        ]);

        return response()->json(['ok' => true, 'redirect' => route('vo.index')]);
    }

    protected function scores(PartieVO $partie): array
    {
        $couple = $partie->couple;

        return [
            'joueur1' => [
                'id' => $couple->user1_id,
                'name' => $couple->user1?->name,
                'score' => $partie->score_joueur1,
            ],
            'joueur2' => [
                'id' => $couple->user2_id,
                'name' => $couple->user2?->name,
                'score' => $partie->score_joueur2,
            ],
        ];
    }

    protected function addScore(PartieVO $partie, User $joueur, int $montant, string $raison): void
    {
        $couple = $partie->couple;
        Point::add($joueur, $couple, $montant, $raison, 'vo');

        $isUser1 = $couple->user1_id === $joueur->id;
        $actuel = $isUser1 ? $partie->score_joueur1 : $partie->score_joueur2;
        if ($isUser1) {
            $partie->score_joueur1 = max(0, $actuel + $montant);
        } else {
            $partie->score_joueur2 = max(0, $actuel + $montant);
        }
        $partie->save();
    }

    protected function switchTurn(PartieVO $partie): void
    {
        $couple = $partie->couple;
        $next = $couple->user1_id === $partie->joueur_actif_id
            ? $couple->user2_id
            : $couple->user1_id;

        $partie->forceFill(['joueur_actif_id' => $next])->save();
    }

    protected function authorizeCouple(PartieVO $partie): void
    {
        abort_if($partie->couple_id !== Auth::user()->couple_id, 403);
    }

    protected function authorizeActif(PartieVO $partie): void
    {
        abort_if($partie->joueur_actif_id !== Auth::user()->id, 403);
    }
}
