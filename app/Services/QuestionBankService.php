<?php

namespace App\Services;

use App\Models\CarteAction;
use App\Models\CarteVerite;
use App\Models\Couple;
use App\Models\Gage;
use App\Models\PartieQuestionQuiDeNous;
use App\Models\PartieVO;
use App\Models\QuestionOuiNon;
use App\Models\QuestionQuiDeNous;
use App\Models\QuestionQuiz;
use App\Models\QuizSessionQuestion;
use App\Models\ReponseOuiNon;
use App\Models\TourVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class QuestionBankService
{
    /**
     * Tire une carte aléatoire en évitant les textes déjà utilisés
     * pour le couple. Retombe sur l'ensemble du pool si tout a été consommé.
     *
     * @param  Builder<Model>  $query  Requête dont le texte à contrôler est la colonne `texte`.
     * @param  string[]  $textesVus  Textes déjà rencontrés par le couple.
     */
    public function carteAleatoire(Builder $query, array $textesVus): ?Model
    {
        if ($textesVus !== []) {
            $inédite = $query->clone()
                ->whereNotIn('texte', $textesVus)
                ->inRandomOrder()
                ->first();

            if ($inédite !== null) {
                return $inédite;
            }
        }

        return $query->inRandomOrder()->first();
    }

    /**
     * Sélectionne $nombre questions avec des textes distincts : les jamais vues d'abord,
     * puis recyclage du pool complet (hors tirage courant) pour compléter.
     *
     * @param  Builder<Model>  $query
     * @param  string[]  $textesVus
     * @return Collection<int, Model>
     */
    public function questionsAleatoires(Builder $query, array $textesVus, int $nombre, string $colonne = 'texte'): Collection
    {
        $retenues = collect();

        if ($textesVus !== []) {
            $retenues = $this->distinctShuffle($query->clone()->whereNotIn($colonne, $textesVus), $colonne, $nombre);
        }

        if ($retenues->count() < $nombre) {
            $manquantes = $nombre - $retenues->count();
            $déjàPrélevées = $retenues->pluck($colonne)->all();

            $pool = $déjàPrélevées === []
                ? $query->clone()
                : $query->clone()->whereNotIn($colonne, $déjàPrélevées);

            $recyclées = $this->distinctShuffle($pool, $colonne, $manquantes);

            $retenues = $retenues->concat($recyclées);
        }

        return $retenues->values();
    }

    public function titrageVerite(PartieVO $partie): ?CarteVerite
    {
        return $this->carteAleatoire(
            CarteVerite::where('niveau', $partie->niveau),
            $this->veritesVues($partie->couple)
        );
    }

    public function titrageAction(PartieVO $partie): ?CarteAction
    {
        return $this->carteAleatoire(
            CarteAction::where('niveau', $partie->niveau),
            $this->actionsVues($partie->couple)
        );
    }

    public function titrageGage(Couple $couple): ?Gage
    {
        return $this->carteAleatoire(Gage::query(), $this->gagesVus($couple));
    }

    /**
     * @return Collection<int, QuestionOuiNon>
     */
    public function questionsOuiNonPour(Couple $couple, int $nombre): Collection
    {
        return $this->questionsAleatoires(QuestionOuiNon::query(), $this->questionsOuiNonVues($couple), $nombre);
    }

    /**
     * @return Collection<int, QuestionQuiDeNous>
     */
    public function questionsQuiDeNousPour(Couple $couple, int $nombre): Collection
    {
        $deux = [$couple->user1_id, $couple->user2_id];

        return $this->questionsAleatoires(
            QuestionQuiDeNous::whereNull('created_by')->orWhereIn('created_by', $deux),
            $this->questionsQuiDeNousVues($couple),
            $nombre
        );
    }

    /**
     * @return Collection<int, QuestionQuiz>
     */
    public function questionsQuizPour(Couple $couple, int $nombre): Collection
    {
        return $this->questionsAleatoires(
            QuestionQuiz::query(),
            $this->questionsQuizVues($couple),
            $nombre,
            'texte_soi'
        );
    }

    /**
     * Tirages distincts (par colonne) puis mélange et premières $nombre.
     *
     * @return Collection<int, Model>
     */
    private function distinctShuffle(Builder $query, string $colonne, int $nombre): Collection
    {
        return $query->get()
            ->unique($colonne)
            ->shuffle()
            ->take($nombre)
            ->values();
    }

    /**
     * @return string[] Textes déjà vus par le couple.
     */
    private function veritesVues(Couple $couple): array
    {
        return $this->textesCartsVus($couple, 'verite', CarteVerite::class);
    }

    /**
     * @return string[]
     */
    private function actionsVues(Couple $couple): array
    {
        return $this->textesCartsVus($couple, 'action', CarteAction::class);
    }

    /**
     * @return string[]
     */
    private function gagesVus(Couple $couple): array
    {
        return $this->textesCartsVus($couple, 'gage', Gage::class);
    }

    /**
     * @param  class-string<Model>  $modele
     * @return string[]
     */
    private function textesCartsVus(Couple $couple, string $type, string $modele): array
    {
        $carteIds = TourVO::query()
            ->where('type', $type)
            ->whereNotNull('carte_id')
            ->whereHas('partie', fn (Builder $query) => $query->where('couple_id', $couple->id))
            ->pluck('carte_id');

        return $modele::whereIn('id', $carteIds)
            ->distinct()
            ->pluck('texte')
            ->all();
    }

    /**
     * @return string[]
     */
    private function questionsOuiNonVues(Couple $couple): array
    {
        $ids = ReponseOuiNon::query()
            ->whereHas('partie', fn (Builder $query) => $query->where('couple_id', $couple->id))
            ->distinct()
            ->pluck('question_id');

        return QuestionOuiNon::whereIn('id', $ids)
            ->distinct()
            ->pluck('texte')
            ->all();
    }

    /**
     * @return string[]
     */
    private function questionsQuiDeNousVues(Couple $couple): array
    {
        $ids = PartieQuestionQuiDeNous::query()
            ->whereHas('partie', fn (Builder $query) => $query->where('couple_id', $couple->id))
            ->distinct()
            ->pluck('question_id');

        return QuestionQuiDeNous::whereIn('id', $ids)
            ->distinct()
            ->pluck('texte')
            ->all();
    }

    /**
     * @return string[]
     */
    private function questionsQuizVues(Couple $couple): array
    {
        $ids = QuizSessionQuestion::query()
            ->whereHas('session', fn (Builder $query) => $query->where('couple_id', $couple->id))
            ->distinct()
            ->pluck('question_id');

        return QuestionQuiz::whereIn('id', $ids)
            ->distinct()
            ->pluck('texte_soi')
            ->all();
    }
}
