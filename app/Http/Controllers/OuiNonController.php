<?php

namespace App\Http\Controllers;

use App\Models\MissionOuiNon;
use App\Models\PartieOuiNon;
use App\Models\Point;
use App\Models\QuestionOuiNon;
use App\Models\ReponseOuiNon;
use App\Services\ActivityService;
use App\Services\PushService;
use App\Services\RecompenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OuiNonController extends Controller
{
    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        $partie = PartieOuiNon::where('couple_id', $couple->id)
            ->whereIn('status', ['en_attente', 'en_cours'])
            ->latest()
            ->first();

        return view('jeux.ouinon.index', [
            'couple' => $couple,
            'partie' => $partie,
            'missions' => $couple->missionsOuiNon->sortByDesc('created_at'),
            'historique' => PartieOuiNon::where('couple_id', $couple->id)
                ->where('status', 'terminee')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $couple = $request->user()->coupleModel;

        PartieOuiNon::where('couple_id', $couple->id)
            ->whereIn('status', ['en_attente', 'en_cours'])
            ->update(['status' => 'terminee']);

        $questions = QuestionOuiNon::inRandomOrder()->limit(10)->get();

        $partie = PartieOuiNon::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'status' => 'en_cours',
        ]);

        foreach ($questions as $question) {
            ReponseOuiNon::create(['partie_id' => $partie->id, 'question_id' => $question->id, 'joueur_id' => $couple->user1_id]);
            ReponseOuiNon::create(['partie_id' => $partie->id, 'question_id' => $question->id, 'joueur_id' => $couple->user2_id]);
        }

        ActivityService::touch($request->user());
        app(PushService::class)->sendToUser($couple->partnerOf($request->user()), [
            'title' => '🎮 Nouvelle partie Oui/Non !',
            'body' => '10 questions t\'attendent, réponds en secret !',
            'url' => route('ouinon.jouer', $partie),
        ]);

        return redirect()->route('ouinon.jouer', $partie);
    }

    public function play(PartieOuiNon $partie): View
    {
        $this->authorizeCouple($partie);
        ActivityService::touch(Auth::user());

        return view('jeux.ouinon.jouer', ['partie' => $partie]);
    }

    public function state(PartieOuiNon $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $user = Auth::user();
        $couple = $partie->couple;
        $partner = $couple->partnerOf($user);

        $questions = $partie->reponses()
            ->with('question')
            ->where('joueur_id', $couple->user1_id)
            ->get()
            ->concat($partie->reponses()->with('question')->where('joueur_id', $couple->user2_id)->get())
            ->groupBy('question_id');

        $items = [];
        foreach ($questions as $questionId => $reponses) {
            $question = $reponses->first()->question;
            $maReponse = $reponses->firstWhere('joueur_id', $user->id)?->reponse;
            $saReponse = $reponses->firstWhere('joueur_id', '!=', $user->id)?->reponse;

            $revetee = $maReponse && $saReponse;
            $resultat = null;
            if ($revetee) {
                $resultat = $maReponse === $saReponse && $maReponse === 'oui'
                    ? 'double_oui'
                    : ($maReponse === $saReponse && $maReponse === 'non'
                        ? 'double_non'
                        : 'divergence');
            }

            $items[] = [
                'id' => $question->id,
                'texte' => $question->texte,
                'categorie' => $question->categorie,
                'maReponse' => $maReponse,
                'saReponse' => $saReponse,
                'revetee' => $revetee,
                'resultat' => $resultat,
                'explication' => $reponses->firstWhere('joueur_id', '!=', $user->id)?->explication,
                'maExplication' => $reponses->firstWhere('joueur_id', $user->id)?->explication,
                'peutExpliquer' => $partie->status === 'terminee' || $revetee && $resultat === 'divergence',
            ];
        }

        $mesReponses = $partie->reponses()->where('joueur_id', $user->id)->whereNotNull('reponse')->count();
        $sesReponses = $partie->reponses()->where('joueur_id', '!=', $user->id)->whereNotNull('reponse')->count();

        return response()->json([
            'status' => $partie->status,
            'nbQuestions' => count($items),
            'mesReponses' => $mesReponses,
            'sesReponses' => $sesReponses,
            'questions' => $items,
            'couple' => [
                'p1' => ['id' => $couple->user1_id, 'name' => $couple->user1?->name],
                'p2' => ['id' => $couple->user2_id, 'name' => $couple->user2?->name],
            ],
        ]);
    }

    public function repond(Request $request, PartieOuiNon $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions_oui_non,id'],
            'reponse' => ['required', 'in:oui,non'],
        ]);

        $reponse = ReponseOuiNon::where('partie_id', $partie->id)
            ->where('question_id', $data['question_id'])
            ->where('joueur_id', $request->user()->id)
            ->first();

        if (! $reponse) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        if ($reponse->reponse) {
            return response()->json(['error' => 'Tu as déjà répondu à cette question.'], 422);
        }

        if ($partie->status !== 'en_cours') {
            return response()->json(['error' => 'La partie est terminée.'], 422);
        }

        $reponse->forceFill(['reponse' => $data['reponse']])->save();

        $partner = $partie->couple->partnerOf($request->user());

        // Vérifier si les deux ont répondu à toutes les questions → terminer
        $total = $partie->reponses()->count() / 2;
        $reponsesP1 = $partie->reponses()->where('joueur_id', $partie->couple->user1_id)->whereNotNull('reponse')->count();
        $reponsesP2 = $partie->reponses()->where('joueur_id', $partie->couple->user2_id)->whereNotNull('reponse')->count();

        if ($total > 0 && $reponsesP1 >= $total && $reponsesP2 >= $total) {
            $this->finalize($partie);
        }

        return response()->json(['ok' => true]);
    }

    public function expliquer(Request $request, PartieOuiNon $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'explication' => ['required', 'string', 'max:500'],
        ]);

        $reponse = ReponseOuiNon::where('partie_id', $partie->id)
            ->where('question_id', $data['question_id'])
            ->where('joueur_id', $request->user()->id)
            ->first();

        if (! $reponse) {
            return response()->json(['error' => 'Introuvable.'], 422);
        }

        $reponse->forceFill(['explication' => $data['explication']])->save();

        app(PushService::class)->sendToUser($partie->couple->partnerOf($request->user()), [
            'title' => '💬 Une explication est arrivée !',
            'body' => $request->user()->name.' explique son NON sur « '.$reponse->question->texte.' »',
            'url' => route('ouinon.jouer', $partie),
        ]);

        return response()->json(['ok' => true]);
    }

    protected function finalize(PartieOuiNon $partie): void
    {
        $couple = $partie->couple;
        $partie->status = 'terminee';
        $partie->save();

        $questions = $partie->reponses()
            ->select('question_id')
            ->where('joueur_id', $couple->user1_id)
            ->distinct()
            ->pluck('question_id');

        foreach ($questions as $questionId) {
            $r1 = $partie->reponses()->where('question_id', $questionId)->where('joueur_id', $couple->user1_id)->first();
            $r2 = $partie->reponses()->where('question_id', $questionId)->where('joueur_id', $couple->user2_id)->first();

            if ($r1?->reponse === 'oui' && $r2?->reponse === 'oui') {
                MissionOuiNon::create([
                    'couple_id' => $couple->id,
                    'partie_id' => $partie->id,
                    'question_id' => $questionId,
                    'statut' => 'a_realiser',
                ]);

                Point::add($couple->user1, $couple, 5, 'Mission validée (double OUI)', 'oui_non');
                Point::add($couple->user2, $couple, 5, 'Mission validée (double OUI)', 'oui_non');
            }
        }

        RecompenseService::check($couple);

        app(PushService::class)->sendToUser($partie->couple->user1, [
            'title' => '🎉 Révélation Oui/Non !',
            'body' => 'Les réponses sont révélées. Découvrez vos missions !',
            'url' => route('ouinon.jouer', $partie),
        ]);
        app(PushService::class)->sendToUser($partie->couple->user2, [
            'title' => '🎉 Révélation Oui/Non !',
            'body' => 'Les réponses sont révélées. Découvrez vos missions !',
            'url' => route('ouinon.jouer', $partie),
        ]);
    }

    public function realiserMission(Request $request, MissionOuiNon $mission): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($mission->couple_id !== $couple->id, 403);

        if ($mission->statut !== 'a_realiser') {
            return response()->json(['error' => 'Mission déjà traitée.'], 422);
        }

        $mission->forceFill(['statut' => 'realisee', 'realisee_at' => now()])->save();
        Point::add($request->user(), $couple, 15, 'Mission réalisée (Jeu du Oui/Non)', 'oui_non');

        RecompenseService::check($couple);

        return response()->json(['ok' => true, 'message' => 'Mission réalisée : +15 points !']);
    }

    protected function authorizeCouple(PartieOuiNon $partie): void
    {
        abort_if($partie->joueur1_id !== Auth::user()->id && $partie->joueur2_id !== Auth::user()->id, 403);
    }
}
