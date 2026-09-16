<?php

namespace App\Http\Controllers;

use App\Models\QuoridorPartie;
use App\Services\ActivityService;
use App\Services\PushService;
use App\Services\QuoridorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QuoridorController extends Controller
{
    public function __construct(private readonly QuoridorService $quoridor) {}

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        $partie = QuoridorPartie::with('joueur1', 'joueur2', 'vainqueur')
            ->where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->latest('id')
            ->first();

        return view('jeux.quoridor.index', [
            'couple' => $couple,
            'partie' => $partie,
            'historique' => QuoridorPartie::with('joueur1', 'joueur2', 'vainqueur')
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

        $mode = $request->string('mode', QuoridorPartie::MODE_CLASSIQUE)->toString();
        if (! in_array($mode, [QuoridorPartie::MODE_CLASSIQUE, QuoridorPartie::MODE_COURSE], true)) {
            $mode = QuoridorPartie::MODE_CLASSIQUE;
        }

        QuoridorPartie::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->update(['statut' => 'terminee']);

        $partie = $this->quoridor->start($couple, $request->user(), $mode);

        ActivityService::touch($request->user());

        app(PushService::class)->sendToUser($couple->partnerOf($request->user()), [
            'title' => '🧱 Une partie de Quoridor t\'attend !',
            'body' => $request->user()->name.' veut jouer à Quoridor. C\'est à toi de répondre !',
            'url' => route('quoridor.jouer', $partie),
        ]);

        return redirect()->route('quoridor.jouer', $partie);
    }

    public function play(QuoridorPartie $partie): View
    {
        $this->authorizeCouple($partie);
        ActivityService::touch(Auth::user());

        return view('jeux.quoridor.jouer', ['partie' => $partie]);
    }

    public function state(QuoridorPartie $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        return response()->json($this->quoridor->etat($partie, Auth::user()));
    }

    public function action(Request $request, QuoridorPartie $partie): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est déjà terminée.'], 422);
        }

        if ($partie->tour_id !== $user->id) {
            return response()->json(['error' => 'Ce n\'est pas à toi de jouer.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', 'in:pion,mur'],
        ]);

        $resultat = $data['type'] === 'mur'
            ? $this->quoridor->poser($partie, (int) $request->input('r'), (int) $request->input('c'), (string) $request->input('o'))
            : $this->quoridor->bouger($partie, (int) $request->input('row'), (int) $request->input('col'));

        if ($resultat['terminee'] && $resultat['vainqueur'] !== null) {
            $vainqueur = $partie->vainqueur;
            $adversaire = $partie->joueur1->id === $vainqueur->id ? $partie->joueur2 : $partie->joueur1;

            app(PushService::class)->sendToUser($adversaire, [
                'title' => '🏆 Partie de Quoridor terminée !',
                'body' => 'Victoire de '.$vainqueur->name.' !',
                'url' => route('quoridor.jouer', $partie),
            ]);
        }

        return response()->json($resultat);
    }

    public function abandonner(QuoridorPartie $partie): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est déjà terminée.'], 422);
        }

        $vainqueur = $this->quoridor->abandonner($partie, $user);

        app(PushService::class)->sendToUser($vainqueur, [
            'title' => '🏆 Victoire au Quoridor !',
            'body' => $user->name.' a abandonné : tu gagnes la partie !',
            'url' => route('quoridor.jouer', $partie),
        ]);

        return response()->json(['ok' => true, 'message' => 'Partie abandonnée. Victoire de '.$vainqueur->name.' !']);
    }

    protected function authorizeCouple(QuoridorPartie $partie): void
    {
        abort_if(! $partie->jouePar(Auth::user()), 403);
    }
}
