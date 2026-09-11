<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\LudoPartie;
use App\Models\LudoToken;
use App\Models\Point;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LudoService
{
    public const TRACK = 52;

    public const FINISH = 57;

    public const SAFE_CASES = [1, 9, 22, 27, 35, 48];

    public function start(Couple $couple, User $initiateur): LudoPartie
    {
        $partie = LudoPartie::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'statut' => LudoPartie::STATUT_EN_COURS,
            'tour_id' => $initiateur->id,
        ]);

        $couleurs = [$couple->user1_id => LudoToken::COULEUR_ROUGE, $couple->user2_id => LudoToken::COULEUR_BLEUE];

        foreach ($couleurs as $joueurId => $couleur) {
            for ($numero = 0; $numero < 4; $numero++) {
                LudoToken::create([
                    'partie_id' => $partie->id,
                    'joueur_id' => $joueurId,
                    'couleur' => $couleur,
                    'numero' => $numero,
                    'position' => LudoToken::POSITION_BASE,
                ]);
            }
        }

        return $partie;
    }

    /**
     * Lance le dé. En cas d'absence de coup possible, le tour passe directement.
     *
     * @return array{de: int, coups: int[], bonus: bool, passe: bool, message: string}
     */
    public function lancer(LudoPartie $partie, ?int $valeur = null): array
    {
        $de = $valeur ?? random_int(1, 6);

        $coups = $partie->tokensOf($partie->tour_id)
            ->get()
            ->filter(fn (LudoToken $pion) => $this->estJouable($pion, $de))
            ->pluck('id')
            ->values()
            ->all();

        if ($coups === []) {
            $partie->forceFill([
                'tour_id' => $partie->opposantDe($partie->tour)->id,
                'dernier_de' => null,
                'de_passe' => $de,
                'six_compte' => 0,
            ])->save();

            return ['de' => $de, 'coups' => [], 'bonus' => false, 'passe' => true, 'de_passe' => $de, 'message' => 'Aucun coup possible avec ce dé, tour passé au/à la partenaire.'];
        }

        $bonus = $de === 6;

        $partie->forceFill([
            'dernier_de' => $de,
            'de_passe' => null,
            'six_compte' => 0,
        ])->save();

        return [
            'de' => $de,
            'coups' => $coups,
            'bonus' => $bonus,
            'passe' => false,
            'message' => $de === 6 ? 'Tu as un 6 : déplace un pion, puis tu rejoues.' : 'Déplace un de tes pions.',
        ];
    }

    /**
     * Déplace le pion choisi puis gère bonus, changement de tour et victoire.
     *
     * @return array{message: string, mange: bool, arrive: bool, terminee: bool, vainqueur: ?int}
     */
    public function jouer(LudoPartie $partie, int $pionId): array
    {
        $de = $partie->dernier_de;
        $pion = $partie->tokens()->whereKey($pionId)->first();

        if (! $pion || ! $this->estJouable($pion, $de)) {
            abort(422, 'Ce pion ne peut pas bouger avec ce dé.');
        }

        $gagnant = null;
        $mange = false;
        $arrive = false;

        DB::transaction(function () use ($partie, $pion, $de, &$gagnant, &$mange, &$arrive) {
            $mange = $this->deplacer($partie, $pion, $de);
            $arrive = $pion->position === self::FINISH;

            if ($this->aGagne($partie, $pion->joueur_id)) {
                $partie->forceFill([
                    'statut' => LudoPartie::STATUT_TERMINEE,
                    'vainqueur_id' => $pion->joueur_id,
                    'dernier_de' => null,
                    'tour_id' => null,
                ])->save();

                $gagnant = $pion->joueur;

                Point::add($pion->joueur, $partie->couple, 25, 'Victoire au Ludo à deux', 'ludo');
                RecompenseService::check($partie->couple);

                return;
            }

            if ($de === 6) {
                // Bonus : le même joueur relance.
                $partie->forceFill(['dernier_de' => null])->save();
            } else {
                $partie->forceFill([
                    'tour_id' => $partie->opposantDe($pion->joueur)->id,
                    'dernier_de' => null,
                    'six_compte' => 0,
                ])->save();
            }
        });

        return [
            'message' => match (true) {
                $gagnant !== null => '🏆 '.$gagnant->name.' remporte la partie !',
                $arrive => '🏁 Un pion est arrivé !',
                $mange => '💥 Pion adverse renvoyé au départ !',
                default => 'Pion déplacé.',
            },
            'mange' => $mange,
            'arrive' => $arrive,
            'terminee' => $gagnant !== null,
            'vainqueur' => $gagnant?->id,
        ];
    }

    public function abandonner(LudoPartie $partie, User $joueur): User
    {
        $vainqueur = $partie->opposantDe($joueur);

        DB::transaction(function () use ($partie, $vainqueur) {
            $partie->forceFill([
                'statut' => LudoPartie::STATUT_TERMINEE,
                'tour_id' => null,
                'dernier_de' => null,
                'vainqueur_id' => $vainqueur->id,
            ])->save();

            Point::add($vainqueur, $partie->couple, 25, 'Victoire au Ludo à deux (abandon)', 'ludo');
            RecompenseService::check($partie->couple);
        });

        return $vainqueur;
    }

    /**
     * État complet de la partie pour le frontal.
     */
    public function etat(LudoPartie $partie, User $user): array
    {
        $partie->loadMissing('joueur1', 'joueur2', 'vainqueur');

        $de = $partie->dernier_de;
        $legal = [];
        if ($partie->statut === LudoPartie::STATUT_EN_COURS && $partie->tour_id === $user->id && $de !== null) {
            $legal = $partie->tokensOf($user->id)
                ->get()
                ->filter(fn (LudoToken $pion) => $this->estJouable($pion, $de))
                ->pluck('id')
                ->values()
                ->all();
        }

        return [
            'statut' => $partie->statut,
            'tour_id' => $partie->tour_id,
            'dernier_de' => $de,
            'de_passe' => $partie->de_passe,
            'joueurs' => [
                'j1' => ['id' => $partie->joueur1->id, 'name' => $partie->joueur1->name, 'couleur' => 'rouge'],
                'j2' => ['id' => $partie->joueur2->id, 'name' => $partie->joueur2->name, 'couleur' => 'bleue'],
            ],
            'pions' => $partie->tokens()->orderBy('couleur')->orderBy('numero')->get()->map(
                fn (LudoToken $pion) => [
                    'id' => $pion->id,
                    'joueur_id' => $pion->joueur_id,
                    'couleur' => $pion->couleur,
                    'numero' => $pion->numero,
                    'position' => $pion->position,
                    'case' => $pion->position >= 0 && $pion->position < self::TRACK
                        ? $this->casePiste($partie, $pion->joueur_id, $pion->position)
                        : null,
                ]
            )->values(),
            'legal' => $legal,
            'vainqueur_id' => $partie->vainqueur_id,
        ];
    }

    private function deplacer(LudoPartie $partie, LudoToken $pion, int $de): bool
    {
        $nouvelle = $pion->position === LudoToken::POSITION_BASE
            ? 1
            : $pion->position + $de;

        $mange = false;

        if ($nouvelle < self::TRACK) {
            $case = $this->casePiste($partie, $pion->joueur_id, $nouvelle);

            if (! in_array($case, self::SAFE_CASES, true)) {
                $adverses = $partie->tokens()
                    ->where('joueur_id', '!=', $pion->joueur_id)
                    ->whereBetween('position', [0, self::TRACK - 1])
                    ->get();

                foreach ($adverses as $adverse) {
                    if ($this->casePiste($partie, $adverse->joueur_id, $adverse->position) === $case) {
                        $adverse->forceFill(['position' => LudoToken::POSITION_BASE])->save();
                        $mange = true;
                    }
                }
            }
        }

        $pion->forceFill(['position' => min($nouvelle, self::FINISH)])->save();

        return $mange;
    }

    private function casePiste(LudoPartie $partie, int $joueurId, int $position): int
    {
        return ($this->entreePour($partie, $joueurId) + $position) % self::TRACK;
    }

    private function entreePour(LudoPartie $partie, int $joueurId): int
    {
        return $joueurId === $partie->joueur1_id ? 0 : 26;
    }

    private function estJouable(LudoToken $pion, int $de): bool
    {
        if ($pion->position === LudoToken::POSITION_BASE) {
            return $de === 6;
        }

        if ($pion->position >= self::FINISH) {
            return false;
        }

        return $pion->position + $de <= self::FINISH;
    }

    private function aGagne(LudoPartie $partie, int $joueurId): bool
    {
        return $partie->tokensOf($joueurId)->where('position', self::FINISH)->count() === 4;
    }
}
