<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('contenu:dedoublonner')]
#[Description('Supprime les doublons de questions/cartes/gages créés par de multiples db:seed, en réaffectant les référencements existants sur la ligne conservée.')]
class DedoublonnerContenu extends Command
{
    /**
     * Tables de contenu à assainir : clé = table, `cle` = colonnes qui
     * définissent une même carte (à regrouper), `enfants` = tables enfants
     * dont les clés étrangères doivent être reportées sur la ligne gardée.
     *
     * @var array<string, array{cle: string[], enfants: string[][]}>
     */
    private const TABLES = [
        'cartes_verite' => [
            'cle' => ['texte', 'niveau'],
            'enfants' => [['tours_vo', 'carte_id']],
        ],
        'cartes_action' => [
            'cle' => ['texte', 'niveau'],
            'enfants' => [['tours_vo', 'carte_id']],
        ],
        'gages' => [
            'cle' => ['texte'],
            'enfants' => [['tours_vo', 'carte_id']],
        ],
        'defis_enveloppes' => [
            'cle' => ['texte', 'couleur'],
            'enfants' => [['enveloppes', 'defi_id']],
        ],
        'questions_oui_non' => [
            'cle' => ['texte'],
            'enfants' => [
                ['reponses_oui_non', 'question_id'],
                ['missions_oui_non', 'question_id'],
            ],
        ],
        'questions_quiz' => [
            'cle' => ['texte_soi'],
            'enfants' => [['quiz_session_questions', 'question_id']],
        ],
        'questions_qui_de_nous' => [
            'cle' => ['texte'],
            'enfants' => [['parties_qui_de_nous_questions', 'question_id']],
        ],
        'questions_du_jour' => [
            'cle' => ['texte'],
            'enfants' => [['questions_journalieres', 'question_id']],
        ],
    ];

    public function handle(): int
    {
        $supprimees = DB::transaction(function () {
            $total = 0;

            foreach (self::TABLES as $table => $config) {
                $total += $this->assainir($table, $config['cle'], $config['enfants']);
            }

            return $total;
        });

        $this->info("Terminé : {$supprimees} ligne(s) en double supprimée(s).");

        return self::SUCCESS;
    }

    /**
     * Pour chaque groupe de lignes identiques : garde la plus ancienne,
     * réaffecte les enfants sur elle puis supprime les copies.
     *
     * @param  string[]  $cle
     * @param  string[][]  $enfants
     */
    private function assainir(string $table, array $cle, array $enfants): int
    {
        $groupes = DB::table($table)
            ->select(array_merge($cle, [DB::raw('count(*) as n')]))
            ->groupBy($cle)
            ->having('n', '>', 1)
            ->get();

        if ($groupes->isEmpty()) {
            return 0;
        }

        $supprimees = 0;

        foreach ($groupes as $groupe) {
            $lignes = DB::table($table);
            foreach ($cle as $colonne) {
                $lignes->where($colonne, $groupe->{$colonne});
            }

            $ids = $lignes->orderBy('id')->pluck('id');
            $gardenId = $ids->shift();

            $ids->each(function (int $id) use ($table, $gardenId, $enfants): void {
                $this->reaffecter($enfants, $id, $gardenId, $table);
            });

            $supprimees += $ids->count();
            DB::table($table)->whereIn('id', $ids)->delete();
        }

        $this->info("  {$table} : {$supprimees} doublon(s) retiré(s).");

        return $supprimees;
    }

    /**
     * Repointe chaque table enfant du doublon vers la ligne conservée.
     *
     * @param  string[][]  $enfants
     */
    private function reaffecter(array $enfants, int $doublonId, int $gardenId, string $table): void
    {
        foreach ($enfants as [$enfant, $colonne]) {
            if ($enfant === 'tours_vo') {
                $this->reaffecterTours($table, $doublonId, $gardenId);

                continue;
            }

            if ($enfant === 'parties_qui_de_nous_questions') {
                $this->purgerDoublonUnique($enfant, $colonne, $doublonId, $gardenId);
            }

            DB::table($enfant)->where($colonne, $doublonId)->update([$colonne => $gardenId]);
        }
    }

    /**
     * tours_vo.carte_id n'a pas de contrainte : selon le type du tour, la
     * carte peut venir de cartes_verite, cartes_action ou gages. On ne traite
     * que les tours pointant vers la table en cours.
     */
    private function reaffecterTours(string $table, int $doublonId, int $gardenId): void
    {
        $type = match ($table) {
            'cartes_verite' => 'verite',
            'cartes_action' => 'action',
            'gages' => 'gage',
            default => null,
        };

        if ($type === null) {
            return;
        }

        DB::table('tours_vo')
            ->where('type', $type)
            ->where('carte_id', $doublonId)
            ->update(['carte_id' => $gardenId]);
    }

    /**
     * parties_qui_de_nous_questions est unique(partie_id, question_id) : si la
     * même partie référence déjà la question conservée, on retire la ligne en
     * double au lieu de violer la contrainte.
     */
    private function purgerDoublonUnique(string $table, string $colonne, int $doublonId, int $gardenId): void
    {
        // Deux étapes : lire les id d'abord, sinon MySQL refuse un DELETE dont
        // la sous-requête lit la même table (erreur 1093).
        $ids = DB::table("{$table} as p")
            ->where("p.{$colonne}", $doublonId)
            ->whereExists(function (Builder $query) use ($table, $colonne, $gardenId): void {
                $query->selectRaw('1')
                    ->from("{$table} as c")
                    ->whereColumn('c.partie_id', 'p.partie_id')
                    ->where("c.{$colonne}", $gardenId);
            })
            ->pluck('p.id');

        if ($ids->isNotEmpty()) {
            DB::table($table)->whereIn('id', $ids)->delete();
        }
    }
}
