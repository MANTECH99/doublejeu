<?php

namespace App\Http\Controllers;

use App\Models\QuestionJournaliere;
use App\Models\ReponseQuestionJournaliere;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QuestionJourController extends Controller
{
    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        [$aujourdhui, $cree] = QuestionJournaliere::genererPourCouple($couple);
        if ($cree && $aujourdhui) {
            $this->notifierNouvelleQuestion($couple);
        }

        $historique = QuestionJournaliere::where('couple_id', $couple->id)
            ->whereDate('jour', '<', today())
            ->with('question', 'reponses.joueur')
            ->orderByDesc('jour')
            ->take(7)
            ->get()
            ->filter(fn (QuestionJournaliere $qj) => $qj->reponses->count() === 2)
            ->values();

        return view('jeux.question-jour.index', [
            'couple' => $couple,
            'aujourdhui' => $aujourdhui,
            'historique' => $historique,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        $user = $request->user();
        $partner = $couple->partnerOf($user);

        [$qj, $cree] = QuestionJournaliere::genererPourCouple($couple);
        if ($cree && $qj) {
            $this->notifierNouvelleQuestion($couple);
        }

        if (! $qj) {
            return response()->json(['error' => 'Aucune question disponible.'], 422);
        }

        $maReponse = $qj->reponses()->where('joueur_id', $user->id)->first()?->reponse;
        $saReponse = $qj->reponses()->where('joueur_id', '!=', $user->id)->first()?->reponse;
        $revelee = $maReponse && $saReponse;

        return response()->json([
            'date' => $qj->jour->format('d/m/Y'),
            'texte' => $qj->question->texte,
            'categorie' => $qj->question->categorie,
            'jaiRepondu' => ! is_null($maReponse),
            'ilElleARepondu' => ! is_null($saReponse),
            'revelee' => (bool) $revelee,
            'maReponse' => $maReponse,
            'saReponse' => $revelee ? $saReponse : null,
            'partenaire' => $partner->name,
        ]);
    }

    public function repondre(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reponse' => ['required', 'string', 'max:500'],
        ]);

        $couple = $request->user()->coupleModel;
        $user = $request->user();

        [$qj, $cree] = QuestionJournaliere::genererPourCouple($couple);
        if ($cree && $qj) {
            $this->notifierNouvelleQuestion($couple);
        }

        if (! $qj) {
            return response()->json(['error' => 'Aucune question disponible.'], 422);
        }

        $existing = ReponseQuestionJournaliere::where('question_journaliere_id', $qj->id)
            ->where('joueur_id', $user->id)
            ->first();

        if ($existing?->reponse) {
            return response()->json(['error' => 'Tu as déjà répondu à la question du jour.'], 422);
        }

        $reponse = $existing ?? new ReponseQuestionJournaliere(['question_journaliere_id' => $qj->id, 'joueur_id' => $user->id]);
        $reponse->forceFill(['reponse' => $data['reponse']])->save();

        $partner = $couple->partnerOf($user);

        app(PushService::class)->sendToUser($partner, [
            'title' => '🌅 Question du jour !',
            'body' => $user->name.' a répondu. Réponds pour découvrir sa réponse !',
            'url' => route('question.index'),
        ]);

        return response()->json(['ok' => true]);
    }

    private function notifierNouvelleQuestion($couple): void
    {
        foreach ($couple->users as $u) {
            app(PushService::class)->sendToUser($u, [
                'title' => '🌅 La Question du jour est là !',
                'body' => 'Réponds en secret pour découvrir la réponse de ton/ta partenaire.',
                'url' => route('question.index'),
            ]);
        }
    }
}
