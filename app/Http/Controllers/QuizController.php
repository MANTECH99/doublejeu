<?php

namespace App\Http\Controllers;

use App\Models\Point;
use App\Models\QuestionQuiz;
use App\Models\QuizReponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    const NB_QUESTIONS = 8; // 4 par cible

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        $session = QuizSession::with('joueur1', 'joueur2')
            ->where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->latest('id')
            ->first();

        return view('jeux.quiz.index', [
            'couple' => $couple,
            'session' => $session,
            'historique' => QuizSession::where('couple_id', $couple->id)
                ->where('statut', 'terminee')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $couple = $request->user()->coupleModel;

        QuizSession::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->update(['statut' => 'terminee']);

        $questions = QuestionQuiz::inRandomOrder()->get();
        if ($questions->count() < 2) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Pas assez de questions disponibles pour lancer une partie.']);
        }

        $session = QuizSession::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'statut' => 'en_cours',
        ]);

        $parCible = intdiv(self::NB_QUESTIONS, 2);
        $ordre = 0;
        $pool = $questions->values();

        foreach ([$couple->user1_id, $couple->user2_id] as $cibleId) {
            for ($i = 0; $i < $parCible; $i++) {
                $question = $pool[$ordre % $pool->count()];
                QuizSessionQuestion::create([
                    'session_id' => $session->id,
                    'question_id' => $question->id,
                    'cible_id' => $cibleId,
                    'ordre' => $ordre++,
                ]);
            }
        }

        ActivityService::touch($request->user());

        app(PushService::class)->sendToUser($couple->partnerOf($request->user()), [
            'title' => '❓ Tu me connais ?',
            'body' => $request->user()->name.' a lancé une partie de '.self::NB_QUESTIONS.' questions. À toi de prouver que tu le/la connais !',
            'url' => route('quiz.jouer', $session),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('quiz.jouer', $session)]);
        }

        return redirect()->route('quiz.jouer', $session);
    }

    public function play(QuizSession $session): View
    {
        $this->authorizeCouple($session);
        ActivityService::touch(Auth::user());

        return view('jeux.quiz.jouer', ['session' => $session]);
    }

    public function state(QuizSession $session): JsonResponse
    {
        $this->authorizeCouple($session);

        $user = Auth::user();
        $couple = $session->couple;
        $partner = $couple->partnerOf($user);

        $items = $session->sessionQuestions()
            ->with('question', 'cible', 'reponses')
            ->get()
            ->map(fn (QuizSessionQuestion $sq) => [
                'id' => $sq->id,
                'texte' => $sq->cible_id === $user->id ? $sq->question->texte_soi : $sq->question->texte_partenaire,
                'cible' => $sq->cible?->name,
                'jeSuisCible' => $sq->cible_id === $user->id,
                'maReponse' => $sq->reponses->firstWhere('joueur_id', $user->id)?->reponse,
                'saReponse' => $sq->reponses->firstWhere('joueur_id', '!=', $user->id)?->reponse,
                'resultat' => $sq->resultat,
                'bonneReponse' => $sq->bonne_reponse,
                'point' => $sq->resultat === 'match' && $sq->cible_id !== $user->id,
            ])
            ->values();

        $mesReponses = $session->sessionQuestions()
            ->whereHas('reponses', fn ($q) => $q->where('joueur_id', $user->id)->whereNotNull('reponse'))
            ->count();
        $sesReponses = $session->sessionQuestions()
            ->whereHas('reponses', fn ($q) => $q->where('joueur_id', '!=', $user->id)->whereNotNull('reponse'))
            ->count();
        $aRepondre = $session->sessionQuestions()->where('cible_id', '!=', $user->id)->count();

        return response()->json([
            'status' => $session->statut,
            'nbQuestions' => $items->count(),
            'aRepondre' => $aRepondre,
            'mesReponses' => $mesReponses,
            'sesReponses' => $sesReponses,
            'questions' => $items,
            'partner' => ['id' => $partner->id, 'name' => $partner->name],
        ]);
    }

    public function repondre(Request $request, QuizSession $session): JsonResponse
    {
        $this->authorizeCouple($session);

        if ($session->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est terminée.'], 422);
        }

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:quiz_session_questions,id'],
            'reponse' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $sq = QuizSessionQuestion::where('id', $data['question_id'])
            ->where('session_id', $session->id)
            ->first();

        if (! $sq) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        // Seul le devinant (celui qui n'est pas la cible) répond.
        if ($sq->cible_id === $user->id) {
            return response()->json(['error' => 'Tu juges cette question, tu n\'y réponds pas.'], 422);
        }

        if ($sq->resultat !== null) {
            return response()->json(['error' => 'Cette question est déjà jugée.'], 422);
        }

        $existing = QuizReponse::where('session_question_id', $sq->id)
            ->where('joueur_id', $user->id)
            ->first();

        if ($existing?->reponse) {
            return response()->json(['error' => 'Tu as déjà répondu à cette question.'], 422);
        }

        $reponse = $existing ?? new QuizReponse(['session_question_id' => $sq->id, 'joueur_id' => $user->id]);
        $reponse->forceFill(['reponse' => $data['reponse']])->save();

        $this->terminerSiFini($session);

        return response()->json(['ok' => true]);
    }

    /**
     * La cible juge la réponse du devinant : vrai (match) ou faux (manque + vraie réponse).
     */
    public function juger(Request $request, QuizSession $session): JsonResponse
    {
        $this->authorizeCouple($session);

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:quiz_session_questions,id'],
            'correct' => ['required', 'boolean'],
            'bonne_reponse' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $sq = QuizSessionQuestion::where('id', $data['question_id'])
            ->where('session_id', $session->id)
            ->with('reponses')
            ->first();

        if (! $sq) {
            return response()->json(['error' => 'Question introuvable.'], 422);
        }

        // Seul la cible (celle dont on parle) peut juger.
        if ($sq->cible_id !== $user->id) {
            return response()->json(['error' => 'Seul(e) '.$sq->cible?->name.' peut juger cette réponse.'], 403);
        }

        // Le/La partenaire (le devinant) doit avoir répondu avant de juger.
        if ($sq->reponses()->count() < 1) {
            return response()->json(['error' => 'Le/La partenaire n\'a pas encore répondu.'], 422);
        }

        if ($sq->resultat !== null) {
            return response()->json(['error' => 'Cette question est déjà jugée.'], 422);
        }

        $correct = (bool) $data['correct'];
        if (! $correct && trim((string) ($data['bonne_reponse'] ?? '')) === '') {
            return response()->json(['error' => "Donne la vraie réponse quand c'est faux."], 422);
        }
        $bonne = $correct ? null : trim((string) $data['bonne_reponse']);

        DB::transaction(function () use ($sq, $session, $correct, $bonne) {
            QuizSessionQuestion::where('id', $sq->id)
                ->whereNull('resultat')
                ->update([
                    'resultat' => $correct ? 'match' : 'manque',
                    'bonne_reponse' => $bonne,
                ]);

            if ($correct) {
                // Le devinant marque des points (celui qui n'est pas la cible).
                $devinantId = $sq->cible_id === $session->joueur1_id ? $session->joueur2_id : $session->joueur1_id;
                Point::add(
                    $session->couple->users()->find($devinantId),
                    $session->couple,
                    10,
                    'Tu me connais ! (jugé vrai par sa/son partenaire)',
                    'quiz'
                );
            }
        });

        $this->terminerSiFini($session);

        return response()->json(['ok' => true]);
    }

    protected function terminerSiFini(QuizSession $session): void
    {
        $total = $session->sessionQuestions()->count();
        $terminees = $session->sessionQuestions()->whereNotNull('resultat')->count();

        if ($total > 0 && $terminees >= $total) {
            $session->forceFill(['statut' => 'terminee'])->save();
        }
    }

    protected function authorizeCouple(QuizSession $session): void
    {
        abort_if($session->joueur1_id !== Auth::user()->id && $session->joueur2_id !== Auth::user()->id, 403);
    }
}
