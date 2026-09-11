<?php

namespace App\Http\Controllers;

use App\Models\LudoPartie;
use App\Services\ActivityService;
use App\Services\LudoService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LudoController extends Controller
{
    public function __construct(private readonly LudoService $ludo) {}

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        $partie = LudoPartie::with('joueur1', 'joueur2', 'vainqueur')
            ->where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->latest('id')
            ->first();

        return view('jeux.ludo.index', [
            'couple' => $couple,
            'partie' => $partie,
            'historique' => LudoPartie::with('joueur1', 'joueur2', 'vainqueur')
                ->where('couple_id', $couple->id)
                ->where('statut', 'terminee')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $couple = $request->user()->coupleModel;

        LudoPartie::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->update(['statut' => 'terminee']);

        $partie = $this->ludo->start($couple, $request->user());

        ActivityService::touch($request->user());

        app(PushService::class)->sendToUser($couple->partnerOf($request->user()), [
            'title' => '🎲 Une partie de Ludo t\'attend !',
            'body' => $request->user()->name.' lance le dé. C\'est à toi de répondre !',
            'url' => route('ludo.jouer', $partie),
        ]);

        return redirect()->route('ludo.jouer', $partie);
    }

    public function play(LudoPartie $partie): View
    {
        $this->authorizeCouple($partie);
        ActivityService::touch(Auth::user());

        return view('jeux.ludo.jouer', ['partie' => $partie]);
    }

    public function state(LudoPartie $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        return response()->json($this->ludo->etat($partie, Auth::user()));
    }

    public function lancer(LudoPartie $partie): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est déjà terminée.'], 422);
        }

        if ($partie->tour_id !== $user->id) {
            return response()->json(['error' => 'Ce n\'est pas à toi de lancer le dé.'], 422);
        }

        if ($partie->dernier_de !== null) {
            return response()->json(['error' => 'Tu dois d\'abord déplacer un pion.'], 422);
        }

        return response()->json($this->ludo->lancer($partie));
    }

    public function bouger(Request $request, LudoPartie $partie): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est déjà terminée.'], 422);
        }

        if ($partie->tour_id !== $user->id) {
            return response()->json(['error' => 'Ce n\'est pas à toi de jouer.'], 422);
        }

        if ($partie->dernier_de === null) {
            return response()->json(['error' => 'Lance le dé d\'abord.'], 422);
        }

        $data = $request->validate([
            'pion' => ['required', 'integer'],
        ]);

        $pion = $partie->tokens()->whereKey($data['pion'])->first();

        if (! $pion || $pion->joueur_id !== $user->id) {
            return response()->json(['error' => 'Ce pion ne t\'appartient pas.'], 422);
        }

        $resultat = $this->ludo->jouer($partie, $pion->id);

        if ($resultat['terminee']) {
            $vainqueur = $partie->vainqueur;

            app(PushService::class)->sendToUser($partie->joueur1, [
                'title' => '🏆 Partie de Ludo terminée !',
                'body' => 'Victoire de '.$vainqueur->name.' !',
                'url' => route('ludo.jouer', $partie),
            ]);
            app(PushService::class)->sendToUser($partie->joueur2, [
                'title' => '🏆 Partie de Ludo terminée !',
                'body' => 'Victoire de '.$vainqueur->name.' !',
                'url' => route('ludo.jouer', $partie),
            ]);
        }

        return response()->json($resultat);
    }

    public function abandonner(LudoPartie $partie): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est déjà terminée.'], 422);
        }

        $vainqueur = $this->ludo->abandonner($partie, $user);

        app(PushService::class)->sendToUser($vainqueur, [
            'title' => '🏆 Victoire au Ludo !',
            'body' => $user->name.' a abandonné : tu gagnes la partie !',
            'url' => route('ludo.jouer', $partie),
        ]);

        return response()->json(['ok' => true, 'message' => 'Partie abandonnée. Victoire de '.$vainqueur->name.' !']);
    }

    protected function authorizeCouple(LudoPartie $partie): void
    {
        abort_if(! $partie->jouePar(Auth::user()), 403);
    }
}
