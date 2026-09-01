<?php

namespace App\Http\Controllers;

use App\Models\Recompense;
use App\Services\ActivityService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecompenseController extends Controller
{
    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        RecompenseService::check($couple);

        $recompenses = Recompense::where('couple_id', $couple->id)
            ->with('gagnant', 'perdant')
            ->orderByDesc('id')
            ->get();

        $scoreP1 = RecompenseService::personalScore($couple, $couple->user1_id);
        $scoreP2 = RecompenseService::personalScore($couple, $couple->user2_id);

        return view('recompenses.index', [
            'couple' => $couple,
            'recompenses' => $recompenses,
            'me' => Auth::user(),
            'scores' => [
                $couple->user1_id => $scoreP1,
                $couple->user2_id => $scoreP2,
            ],
        ]);
    }

    public function marquer(Recompense $recompense): JsonResponse
    {
        abort_if($recompense->couple_id !== Auth::user()->couple_id, 403);

        if ($recompense->statut === 'due') {
            $recompense->forceFill(['statut' => 'offerte'])->save();
        }

        return response()->json(['ok' => true, 'message' => 'Récompense marquée comme offerte.']);
    }

    public function creer(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $data = $request->validate([
            'texte' => ['required', 'string', 'max:255'],
        ]);

        $partner = $couple->partnerOf($request->user());

        Recompense::create([
            'couple_id' => $couple->id,
            'joueur_gagnant_id' => $request->user()->id,
            'joueur_perdant_id' => $partner->id,
            'texte' => $data['texte'],
            'statut' => 'due',
        ]);

        return response()->json(['ok' => true, 'message' => 'Récompense ajoutée !']);
    }
}
