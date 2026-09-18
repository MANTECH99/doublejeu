<?php

namespace App\Http\Controllers;

use App\Models\Point;
use App\Models\QuizReponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\PushService;
use App\Services\QuestionBankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    const NB_QUESTIONS = 8; // 4 par cible

    public function __construct(private readonly QuestionBankService $bank) {}

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

    public function start(Request $request): RedirectResponse|JsonResponse
    {
        $couple = $request->user()->coupleModel;

        QuizSession::where('couple_id', $couple->id)
            ->where('statut', 'en_cours')
            ->update(['statut' => 'terminee']);

        $questions = $this->bank->questionsQuizPour($couple, self::NB_QUESTIONS);
        if ($questions->count() < 2) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Pas assez de questions disponibles pour lancer une partie.']);
        }

        $session = QuizSession::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'statut' => 'en_cours',
        ]);

        $cibles = [$couple->user1_id, $couple->user2_id];
        $ordre = 0;

        // Les 4 questions sur chaque cible sont entrelacées : les tours
        // au spinner et à la réponse alternent naturellement d'un joueur à l'autre.
        foreach ($questions->values() as $index => $question) {
            QuizSessionQuestion::create([
                'session_id' => $session->id,
                'question_id' => $question->id,
                'cible_id' => $cibles[$index % 2],
                'ordre' => $ordre++,
            ]);
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
        $partner = $user->id === $session->joueur1_id ? $session->joueur2 : $session->joueur1;

        $total = $session->sessionQuestions()->count();

        // Question révélée et en attente de réponse/jugement : c'est elle qu'on joue.
        $enJeu = $session->sessionQuestions()
            ->whereNull('resultat')
            ->whereNotNull('revelee_par_id')
            ->with(['reponses', 'question', 'cible'])
            ->first();

        // Prochaine à révéler : détermine de qui c'est le tour de tourner la roulette.
        $prochaine = $this->prochaineQuestion($session);

        // Dernière jugée : affichée pendant la transition vers le tour suivant.
        $derniere = $session->sessionQuestions()
            ->whereNotNull('resultat')
            ->with(['reponses', 'question', 'cible'])
            ->reorder('revelee_ordre', 'desc')
            ->first();

        $tourDe = null;
        if ($session->statut === 'en_cours' && $enJeu === null && $prochaine !== null) {
            $devinantId = $this->devinantIdDe($session, $prochaine);
            $tourDe = $devinantId === $session->joueur1_id ? $session->joueur1 : $session->joueur2;
        }

        $map = fn (QuizSessionQuestion $sq): array => $this->questionData($session, $sq, $user);

        $conclus = $session->statut === 'terminee'
            ? $session->sessionQuestions()->whereNotNull('resultat')->with(['reponses', 'question', 'cible'])->get()->map($map)->values()
            : collect();

        return response()->json([
            'status' => $session->statut,
            'sessionId' => $session->id,
            'total' => $total,
            'revelees' => $session->sessionQuestions()->whereNotNull('revelee_par_id')->count(),
            'jugees' => $session->sessionQuestions()->whereNotNull('resultat')->count(),
            'tourDe' => $tourDe !== null ? ['id' => $tourDe->id, 'name' => $tourDe->name] : null,
            'question' => $enJeu !== null ? $map($enJeu) : null,
            'dernier' => $derniere !== null ? $map($derniere) : null,
            'poolEpuise' => $this->bank->poolQuizEpuise($session->couple),
            'partner' => ['id' => $partner->id, 'name' => $partner->name],
            'moi' => ['id' => $user->id, 'name' => $user->name],
            'conclus' => $conclus,
        ]);
    }

    public function reveler(Request $request, QuizSession $session): JsonResponse
    {
        $this->authorizeCouple($session);

        if ($session->statut !== 'en_cours') {
            return response()->json(['error' => 'La partie est terminée.'], 422);
        }

        $enJeu = $session->sessionQuestions()
            ->whereNull('resultat')
            ->whereNotNull('revelee_par_id')
            ->first();

        if ($enJeu !== null) {
            return response()->json(['error' => 'Une question est déjà en cours.'], 422);
        }

        $suivante = $this->prochaineQuestion($session);

        if ($suivante === null) {
            return response()->json(['error' => 'Toutes les questions sont terminées.'], 422);
        }

        // Seul le devinant (celui que la question ne cible pas) tourne la roulette.
        $devinantId = $this->devinantIdDe($session, $suivante);

        if ($devinantId !== $request->user()->id) {
            $devinant = $devinantId === $session->joueur1_id ? $session->joueur1 : $session->joueur2;

            return response()->json(['error' => "C'est au tour de {$devinant->name} de faire tourner la roulette."], 403);
        }

        $suivante->forceFill([
            'revelee_par_id' => $request->user()->id,
            'revelee_le' => now(),
            'revelee_ordre' => $this->reveleeOrdreSuivant($session),
        ])->save();

        return response()->json(['ok' => true]);
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

        if ($sq->revelee_par_id === null) {
            return response()->json(['error' => "Cette question n'a pas encore été révélée."], 422);
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

    /**
     * Le devinant est celui qui répond à une question : il n'est pas la cible.
     */
    protected function devinantIdDe(QuizSession $session, QuizSessionQuestion $sq): int
    {
        return $sq->cible_id === $session->joueur1_id ? $session->joueur2_id : $session->joueur1_id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function questionData(QuizSession $session, QuizSessionQuestion $sq, User $user): array
    {
        $devinantId = $this->devinantIdDe($session, $sq);
        $devinant = $devinantId === $session->joueur1_id ? $session->joueur1 : $session->joueur2;

        return [
            'id' => $sq->id,
            'ordre' => $sq->ordre,
            'texte' => $sq->cible_id === $user->id ? $sq->question->texte_soi : $sq->question->texte_partenaire,
            'categorie' => $sq->question->categorie,
            'cible' => $sq->cible?->name,
            'jeSuisCible' => $sq->cible_id === $user->id,
            'maReponse' => $sq->reponses->firstWhere('joueur_id', $user->id)?->reponse,
            'saReponse' => $sq->reponses->firstWhere('joueur_id', '!=', $user->id)?->reponse,
            'resultat' => $sq->resultat,
            'bonneReponse' => $sq->bonne_reponse,
            'gagneur' => $sq->resultat === 'match' ? ['id' => $devinantId, 'name' => $devinant->name] : null,
        ];
    }

    /**
     * Prochaine question à révéler, en forçant l'alternance des cibles : on
     * cible l'autre joueur que la question précédemment révélée. Même si une
     * session a été créée « regroupée » (les 4 questions d'un joueur d'abord),
     * les tours alternent donc une question par joueur.
     */
    protected function prochaineQuestion(QuizSession $session): ?QuizSessionQuestion
    {
        $restantes = $session->sessionQuestions()
            ->whereNull('resultat')
            ->whereNull('revelee_par_id');

        $dernierRevelee = $session->sessionQuestions()
            ->whereNotNull('revelee_par_id')
            ->reorder('revelee_ordre', 'desc')
            ->first();

        if ($dernierRevelee !== null) {
            $cibleAttendue = $dernierRevelee->cible_id === $session->joueur1_id ? $session->joueur2_id : $session->joueur1_id;
            $envisagée = (clone $restantes)->where('cible_id', $cibleAttendue)->orderBy('ordre')->first();

            if ($envisagée !== null) {
                return $envisagée;
            }
        }

        return $restantes->orderBy('ordre')->first();
    }

    protected function reveleeOrdreSuivant(QuizSession $session): int
    {
        $max = $session->sessionQuestions()->max('revelee_ordre') ?? 0;

        return (int) $max + 1;
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
