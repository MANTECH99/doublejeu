<?php

namespace App\Http\Controllers;

use App\Models\PartieQuestionQuiDeNous;
use App\Models\PartieQuiDeNous;
use App\Models\Point;
use App\Models\QuestionQuiDeNous;
use App\Models\ReponseQuiDeNous;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuiDeNousDeuxController extends Controller
{
    const NB_QUESTIONS = 8;

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        $partie = PartieQuiDeNous::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->latest('id')
            ->first();

        return view('jeux.qui-nous-deux.index', [
            'couple' => $couple,
            'partie' => $partie,
            'nbPersoQuestions' => QuestionQuiDeNous::where('created_by', Auth::id())->count(),
            'historique' => PartieQuiDeNous::where('couple_id', $couple->id)
                ->where('statut', 'terminee')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $couple = $request->user()->coupleModel;

        PartieQuiDeNous::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->update(['statut' => 'terminee']);

        $questions = QuestionQuiDeNous::whereNull('created_by')
            ->orWhereIn('created_by', [$couple->user1_id, $couple->user2_id])
            ->inRandomOrder()
            ->limit(self::NB_QUESTIONS)
            ->get();

        if ($questions->count() < 2) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Pas assez de questions disponibles pour lancer une partie.']);
        }

        $partie = PartieQuiDeNous::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'statut' => 'en_cours',
        ]);

        foreach ($questions as $ordre => $question) {
            $pq = PartieQuestionQuiDeNous::create([
                'partie_id' => $partie->id,
                'question_id' => $question->id,
                'ordre' => $ordre,
            ]);

            ReponseQuiDeNous::create(['partie_question_id' => $pq->id, 'joueur_id' => $couple->user1_id]);
            ReponseQuiDeNous::create(['partie_question_id' => $pq->id, 'joueur_id' => $couple->user2_id]);
        }

        ActivityService::touch($request->user());

        app(PushService::class)->sendToUser($couple->partnerOf($request->user()), [
            'title' => '❓ Qui de nous deux ?',
            'body' => $request->user()->name.' a lancé une partie de '.self::NB_QUESTIONS.' questions. Réponds en secret !',
            'url' => route('qdn2.jouer', $partie),
        ]);

        return redirect()->route('qdn2.jouer', $partie);
    }

    public function play(PartieQuiDeNous $partie): View
    {
        $this->authorizeCouple($partie);
        ActivityService::touch(Auth::user());

        return view('jeux.qui-nous-deux.jouer', ['partie' => $partie]);
    }

    public function state(PartieQuiDeNous $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $user = Auth::user();
        $partner = $partie->couple->partnerOf($user);

        $partieQuestions = $partie->partieQuestions()
            ->with('question', 'reponses.joueur')
            ->get();

        $items = $partieQuestions->map(function (PartieQuestionQuiDeNous $pq) use ($partie, $user, $partner) {
            $r1 = $pq->reponses->firstWhere('joueur_id', $partie->joueur1_id);
            $r2 = $pq->reponses->firstWhere('joueur_id', $partie->joueur2_id);

            $maR = $user->id === $partie->joueur1_id ? $r1 : $r2;
            $saR = $user->id === $partie->joueur1_id ? $r2 : $r1;

            $maDesignation = $maR?->designation;
            $saDesignation = $saR?->designation;

            return [
                'id' => $pq->id,
                'texte' => $pq->question->texte,
                'categorie' => $pq->question->categorie,
                'maDesignation' => $maDesignation,
                'saDesignation' => $saDesignation,
                'revelee' => $pq->resultat !== null,
                'resultat' => $pq->resultat,
                'debat_resolu' => (bool) $pq->debat_resolu,
                'maCible' => $maDesignation === 'moi' ? $user->name : ($maDesignation === 'partenaire' ? $partner->name : null),
                'saCible' => $saDesignation === 'moi' ? $partner->name : ($saDesignation === 'partenaire' ? $user->name : null),
            ];
        });

        $reponses = $partieQuestions->flatMap(fn ($pq) => $pq->reponses);

        $mesReponses = $reponses->where('joueur_id', $user->id)->whereNotNull('designation')->count();
        $sesReponses = $reponses->where('joueur_id', '!=', $user->id)->whereNotNull('designation')->count();

        $nbAccords = $partieQuestions->where('resultat', 'accord')->count();
        $mult = $nbAccords * 5;

        return response()->json([
            'status' => $partie->statut,
            'nbQuestions' => $items->count(),
            'mesReponses' => $mesReponses,
            'sesReponses' => $sesReponses,
            'mesPoints' => $mult,
            'sesPoints' => $mult,
            'questions' => $items,
            'couple' => [
                'p1' => ['id' => $partie->joueur1_id, 'name' => $partie->joueur1?->name],
                'p2' => ['id' => $partie->joueur2_id, 'name' => $partie->joueur2?->name],
            ],
        ]);
    }

    public function repondre(Request $request, PartieQuiDeNous $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        if ($partie->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est terminée.'], 422);
        }

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:parties_qui_de_nous_questions,id'],
            'designation' => ['required', 'in:moi,partenaire'],
        ]);

        $pq = PartieQuestionQuiDeNous::where('id', $data['question_id'])
            ->where('partie_id', $partie->id)
            ->first();

        if (! $pq) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        $user = $request->user();

        $reponse = ReponseQuiDeNous::where('partie_question_id', $pq->id)
            ->where('joueur_id', $user->id)
            ->first();

        if (! $reponse) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        if ($reponse->designation) {
            return response()->json(['error' => 'Tu as déjà répondu à cette question.'], 422);
        }

        $reponse->forceFill(['designation' => $data['designation']])->save();

        // L'autre a-t-il déjà répondu ? → la question se révèle et on départage.
        $lesDeux = ReponseQuiDeNous::where('partie_question_id', $pq->id)
            ->whereNotNull('designation')
            ->count();

        if ($lesDeux === 2) {
            $r1 = ReponseQuiDeNous::where('partie_question_id', $pq->id)->where('joueur_id', $partie->joueur1_id)->first();
            $r2 = ReponseQuiDeNous::where('partie_question_id', $pq->id)->where('joueur_id', $partie->joueur2_id)->first();

            // Accord = les deux désignent la même personne.
            $choix1 = $r1->designation === 'moi' ? 'joueur1' : 'joueur2';
            $choix2 = $r2->designation === 'moi' ? 'joueur2' : 'joueur1';
            $accord = $choix1 === $choix2;

            DB::transaction(function () use ($pq, $partie, $accord) {
                PartieQuestionQuiDeNous::where('id', $pq->id)
                    ->whereNull('resultat')
                    ->update(['resultat' => $accord ? 'accord' : 'divergence']);

                if ($accord) {
                    Point::add($partie->couple->user1, $partie->couple, 5, 'Accord (Qui de nous deux ?)', 'qui_de_nous');
                    Point::add($partie->couple->user2, $partie->couple, 5, 'Accord (Qui de nous deux ?)', 'qui_de_nous');
                }
            });

            $this->terminerSiFini($partie);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Marque « On s'est expliqués ✓ » sur une question divergente.
     */
    public function resoudre(Request $request, PartieQuiDeNous $partie): JsonResponse
    {
        $this->authorizeCouple($partie);

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:parties_qui_de_nous_questions,id'],
        ]);

        $pq = PartieQuestionQuiDeNous::where('id', $data['question_id'])
            ->where('partie_id', $partie->id)
            ->first();

        if (! $pq) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        if ($pq->resultat !== 'divergence') {
            return response()->json(['error' => 'Il n\'y a pas de débat ici.'], 422);
        }

        if ($pq->debat_resolu) {
            return response()->json(['error' => 'Ce débat est déjà résolu.'], 422);
        }

        $pq->forceFill(['debat_resolu' => true])->save();

        return response()->json(['ok' => true]);
    }

    public function questions(): View
    {
        ActivityService::touch(Auth::user());

        $user = Auth::user();

        return view('jeux.qui-nous-deux.questions', [
            'couple' => $user->coupleModel,
            'mesQuestions' => QuestionQuiDeNous::where('created_by', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function creerQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'texte' => ['required', 'string', 'max:300'],
            'categorie' => ['required', 'in:personnalite,vie_quotidienne,relation,habitudes'],
        ]);

        QuestionQuiDeNous::create([
            'texte' => $data['texte'],
            'categorie' => $data['categorie'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Question ajoutée à ta banque perso !']);
    }

    public function detruireQuestion(Request $request, QuestionQuiDeNous $question): RedirectResponse
    {
        if ($question->created_by !== $request->user()->id) {
            abort(403);
        }

        $question->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Question supprimée.']);
    }

    protected function terminerSiFini(PartieQuiDeNous $partie): void
    {
        $total = $partie->partieQuestions()->count();
        $revel = $partie->partieQuestions()->whereNotNull('resultat')->count();

        if ($total > 0 && $revel >= $total) {
            $partie->forceFill(['statut' => 'terminee'])->save();
        }
    }

    protected function authorizeCouple(PartieQuiDeNous $partie): void
    {
        abort_if($partie->joueur1_id !== Auth::user()->id && $partie->joueur2_id !== Auth::user()->id, 403);
    }
}
