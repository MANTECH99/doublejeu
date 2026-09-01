<?php

namespace App\Http\Controllers;

use App\Models\CarteAction;
use App\Models\CarteVerite;
use App\Models\DefiEnveloppe;
use App\Models\Gage;
use App\Models\QuestionOuiNon;
use App\Services\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CardsController extends Controller
{
    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $user = Auth::user();

        return view('cartes.index', [
            'verites' => CarteVerite::whereNull('created_by')->orWhere('created_by', $user->id)->orderBy('niveau')->get(),
            'actions' => CarteAction::whereNull('created_by')->orWhere('created_by', $user->id)->orderBy('niveau')->get(),
            'defis' => DefiEnveloppe::whereNull('created_by')->orWhere('created_by', $user->id)->orderBy('couleur')->get(),
            'questions' => QuestionOuiNon::whereNull('created_by')->orWhere('created_by', $user->id)->orderBy('categorie')->get(),
            'gages' => Gage::whereNull('created_by')->orWhere('created_by', $user->id)->get(),
        ]);
    }

    public function creerVerite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:500'],
            'niveau' => ['required', 'in:doux,chaud,brulant'],
        ]);

        CarteVerite::create([
            'texte' => $data['texte'],
            'niveau' => $data['niveau'],
            'categorie' => 'personnalisee',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Carte Vérité ajoutée !']);
    }

    public function creerAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:500'],
            'niveau' => ['required', 'in:doux,chaud,brulant'],
        ]);

        CarteAction::create([
            'texte' => $data['texte'],
            'niveau' => $data['niveau'],
            'categorie' => 'personnalisee',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Carte Action ajoutée !']);
    }

    public function creerDefi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:500'],
            'couleur' => ['required', 'in:rouge,bleue,verte'],
        ]);

        DefiEnveloppe::create([
            'texte' => $data['texte'],
            'couleur' => $data['couleur'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Défi enveloppe ajouté !']);
    }

    public function creerQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:500'],
            'categorie' => ['required', 'in:vie_quotidienne,intimite,fantasmes,aventure'],
        ]);

        QuestionOuiNon::create([
            'texte' => $data['texte'],
            'categorie' => $data['categorie'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Question Oui/Non ajoutée !']);
    }

    public function creerGage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:500'],
        ]);

        Gage::create([
            'texte' => $data['texte'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Gage ajouté !']);
    }

    public function detruire(string $type, int $id): RedirectResponse
    {
        $user = Auth::user();

        $model = match ($type) {
            'verite' => CarteVerite::class,
            'action' => CarteAction::class,
            'defi' => DefiEnveloppe::class,
            'question' => QuestionOuiNon::class,
            'gage' => Gage::class,
            default => null,
        };

        if ($model) {
            $item = $model::find($id);
            if ($item && $item->created_by === $user->id) {
                $item->delete();
            }
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Contenu supprimé.']);
    }
}
