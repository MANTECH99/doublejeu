<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\DefiEnveloppe;
use App\Models\Enveloppe;
use App\Models\Point;
use App\Models\Recompense;
use App\Services\ActivityService;
use App\Services\PushService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EnveloppeController extends Controller
{
    const COULEURS_LABELS = [
        'rouge' => 'Osé',
        'bleue' => 'Tendre',
        'verte' => 'Drôle',
    ];

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        return view('jeux.enveloppe.index', [
            'couple' => $couple,
            'enveloppes' => $couple->enveloppes()->with('defi', 'joueur')->orderBy('id')->get(),
        ]);
    }

    public function nouvelle(Request $request): RedirectResponse
    {
        $couple = $request->user()->coupleModel;

        $couple->enveloppes()->delete();

        foreach ([$couple->user1_id, $couple->user2_id] as $joueurId) {
            foreach (['rouge', 'bleue', 'verte'] as $couleur) {
                $defis = DefiEnveloppe::where('couleur', $couleur)
                    ->whereNotIn('id', function ($q) use ($couple) {
                        $q->select('defi_id')->from('enveloppes')->where('couple_id', $couple->id);
                    })
                    ->inRandomOrder()
                    ->limit(3)
                    ->get();

                $defis = $defis->isEmpty()
                    ? DefiEnveloppe::where('couleur', $couleur)->inRandomOrder()->limit(3)->get()
                    : $defis;

                foreach ($defis as $defi) {
                    Enveloppe::create([
                        'couple_id' => $couple->id,
                        'joueur_id' => $joueurId,
                        'couleur' => $couleur,
                        'defi_id' => $defi->id,
                        'statut' => 'disponible',
                    ]);
                }
            }
        }

        ActivityService::touch($request->user());

        return redirect()->route('enveloppe.index');
    }

    public function state(Couple $couple): JsonResponse
    {
        $user = Auth::user();
        abort_if($couple->id !== $user->couple_id, 403);

        [$enveloppes, $p1, $p2, $terminee] = $this->bilan($couple);

        // Dernier acteur
        $dernier = $enveloppes->where('statut', '!=', 'disponible')->sortByDesc('updated_at')->first();
        $actifId = $couple->user1_id;
        if ($dernier) {
            $actifId = $dernier->partie_joueur_qui_realise === $couple->user1_id
                ? $couple->user2_id
                : $couple->user1_id;
        }

        if ($terminee) {
            [$winner, $loser] = $p1['score'] >= $p2['score'] ? [$p1, $p2] : [$p2, $p1];
        }

        $recompenseDue = Recompense::where('couple_id', $couple->id)
            ->where('statut', 'due')
            ->latest('id')
            ->first();

        if ($recompenseDue && $terminee && (int) $recompenseDue->joueur_gagnant_id !== (int) $winner['id']) {
            $recompenseDue->forceFill([
                'joueur_gagnant_id' => $winner['id'],
                'joueur_perdant_id' => $loser['id'],
            ])->save();
        }

        return response()->json([
            'actifId' => $actifId,
            'terminee' => $terminee,
            'recompense' => $recompenseDue ? [
                'gagnant' => $recompenseDue->gagnant?->name,
                'perdant' => $recompenseDue->perdant?->name,
                'texte' => $recompenseDue->texte,
            ] : null,
            'recompenseEnvoyee' => Recompense::where('couple_id', $couple->id)
                ->where('joueur_gagnant_id', $user->id)
                ->where('statut', 'due')
                ->exists(),
            'evenements' => $enveloppes->where('statut', '!=', 'disponible')->sortByDesc('updated_at')->take(8)->map(fn ($e) => [
                'joueur' => $e->joueur?->name,
                'couleur' => $e->couleur,
                'texte' => $e->defi?->texte,
                'statut' => $e->statut,
            ])->values(),
            'joueurs' => [$p1, $p2],
            'enveloppes' => $enveloppes->map(fn ($e) => [
                'id' => $e->id,
                'joueurId' => $e->joueur_id,
                'couleur' => $e->couleur,
                'statut' => $e->statut,
                'defi' => $e->statut === 'disponible' ? null : $e->defi?->texte,
            ]),
        ]);
    }

    private function bilan(Couple $couple): array
    {
        $enveloppes = $couple->enveloppes()->with('defi')->orderBy('id')->get();

        $p1 = ['id' => $couple->user1_id, 'name' => $couple->user1?->name, 'score' => 0, 'realises' => 0, 'refuses' => 0];
        $p2 = ['id' => $couple->user2_id, 'name' => $couple->user2?->name, 'score' => 0, 'realises' => 0, 'refuses' => 0];

        foreach ($enveloppes as $env) {
            $isP2 = $env->partie_joueur_qui_realise === $couple->user2_id && ! is_null($env->partie_joueur_qui_realise);

            if ($isP2) {
                $cible = &$p2;
            } else {
                $cible = &$p1;
            }

            if ($env->statut === 'utilisee' || $env->statut === 'realisee') {
                $cible['score'] += 15;
                $cible['realises']++;
            } elseif ($env->statut === 'refusee') {
                $cible['score'] -= 10;
                $cible['refuses']++;
            }
            unset($cible);
        }

        return [$enveloppes, $p1, $p2, $enveloppes->where('statut', 'disponible')->count() === 0];
    }

    public function ouvrir(Request $request, Couple $couple, Enveloppe $enveloppe): JsonResponse
    {
        $user = $request->user();
        abort_if($couple->id !== $user->couple_id, 403);

        if ($enveloppe->couple_id !== $couple->id) {
            return response()->json(['error' => 'Enveloppe introuvable.'], 404);
        }

        $dernier = $couple->enveloppes()->where('statut', '!=', 'disponible')->orderByDesc('updated_at')->first();
        $actifId = $couple->user1_id;
        if ($dernier) {
            $actifId = $dernier->partie_joueur_qui_realise === $couple->user1_id ? $couple->user2_id : $couple->user1_id;
        }

        if ($enveloppe->joueur_id !== $user->id) {
            return response()->json(['error' => 'Cette enveloppe appartient à ton/ta partenaire.'], 403);
        }

        if ($enveloppe->statut !== 'disponible') {
            return response()->json(['error' => 'Enveloppe déjà ouverte.'], 422);
        }

        if ($actifId !== $user->id) {
            return response()->json(['error' => 'À ton/ta partenaire de jouer en ce moment.'], 422);
        }

        $enveloppe->forceFill([
            'statut' => 'utilisee',
            'partie_joueur_qui_realise' => $user->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'enveloppe_id' => $enveloppe->id,
            'couleur' => $enveloppe->couleur,
            'defi' => $enveloppe->defi->texte,
        ]);
    }

    public function repondre(Request $request, Couple $couple, Enveloppe $enveloppe): JsonResponse
    {
        $user = $request->user();
        abort_if($couple->id !== $user->couple_id, 403);

        $data = $request->validate([
            'accepte' => ['required', 'boolean'],
        ]);

        if ($enveloppe->joueur_id !== $user->id || $enveloppe->statut !== 'utilisee') {
            return response()->json(['error' => 'Action impossible.'], 422);
        }

        if ($data['accepte']) {
            Point::add($user, $couple, 15, 'Défi enveloppe '.self::COULEURS_LABELS[$enveloppe->couleur].' réalisé', 'enveloppe');
            $enveloppe->forceFill(['statut' => 'realisee', 'accepte' => true])->save();
            $message = 'Défi réalisé : +15 points !';
        } else {
            Point::add($user, $couple, -10, 'Défi enveloppe refusé', 'enveloppe');
            $partner = $couple->partnerOf($user);
            Point::add($partner, $couple, 10, 'Enveloppe refusée par le partenaire', 'enveloppe');
            $enveloppe->forceFill(['statut' => 'refusee', 'accepte' => false])->save();
            $message = 'Défi refusé : -10 points, ton/ta partenaire gagne +10.';
        }

        RecompenseService::check($couple);

        $partner = $couple->partnerOf($user);

        app(PushService::class)->sendToUser($partner, [
            'title' => '✉️ Enveloppe ouverte !',
            'body' => $user->name.' a terminé son enveloppe. À ton tour !',
            'url' => route('enveloppe.index'),
        ]);

        return response()->json(['ok' => true, 'message' => $message]);
    }

    public function recompense(Request $request, Couple $couple): JsonResponse
    {
        $user = $request->user();
        abort_if($couple->id !== $user->couple_id, 403);

        $data = $request->validate([
            'perdant_id' => ['required', 'integer'],
            'texte' => ['required', 'string', 'max:255'],
        ]);

        [, $p1, $p2, $terminee] = $this->bilan($couple);

        if (! $terminee) {
            return response()->json(['error' => 'La partie n\'est pas terminée.'], 422);
        }

        [$winner, $loser] = $p1['score'] >= $p2['score'] ? [$p1, $p2] : [$p2, $p1];

        if ((int) $winner['id'] !== (int) $user->id) {
            return response()->json(['error' => 'Seul(e) le/la gagnant(e) peut exiger une récompense.'], 422);
        }

        Recompense::create([
            'couple_id' => $couple->id,
            'joueur_gagnant_id' => $winner['id'],
            'joueur_perdant_id' => $loser['id'],
            'texte' => $data['texte'],
            'statut' => 'due',
        ]);

        app(PushService::class)->sendToUser($couple->users()->find($loser['id']), [
            'title' => '🏆 Récompense !',
            'body' => $winner['name'].' exige une récompense de ta part : '.$data['texte'],
            'url' => route('recompenses.index'),
        ]);

        return response()->json(['ok' => true, 'message' => 'Récompense enregistrée !']);
    }
}
