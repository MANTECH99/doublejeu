<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\Point;
use App\Models\QuoridorPartie;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class QuoridorService
{
    public const TAILLE = 9;

    public const MURS_PAR_JOUEUR = 10;

    private const CASE_DEPART = 4;

    public function start(Couple $couple, User $initiateur, string $mode = QuoridorPartie::MODE_CLASSIQUE): QuoridorPartie
    {
        return QuoridorPartie::create([
            'couple_id' => $couple->id,
            'joueur1_id' => $couple->user1_id,
            'joueur2_id' => $couple->user2_id,
            'statut' => QuoridorPartie::STATUT_EN_COURS,
            'mode' => $mode,
            'tour_id' => $initiateur->id,
            'pions' => $mode === QuoridorPartie::MODE_COURSE
                ? [
                    'j1' => ['row' => self::TAILLE - 1, 'col' => 3],
                    'j2' => ['row' => self::TAILLE - 1, 'col' => 5],
                ]
                : [
                    'j1' => ['row' => self::TAILLE - 1, 'col' => self::CASE_DEPART],
                    'j2' => ['row' => 0, 'col' => self::CASE_DEPART],
                ],
            'murs' => [],
        ]);
    }

    /**
     * Déplace le pion du joueur courant puis change de tour ou clôt la partie.
     *
     * @return array{message: string, terminee: bool, vainqueur: ?int}
     */
    public function bouger(QuoridorPartie $partie, int $row, int $col): array
    {
        $joueurId = $partie->tour_id;
        $pion = $partie->pionDe($joueurId);

        if (! in_array(['row' => $row, 'col' => $col], $this->coupsPion($partie, $pion), true)) {
            abort(422, 'Ce déplacement n\'est pas valide.');
        }

        $gagnant = null;

        DB::transaction(function () use ($partie, $joueurId, $row, $col, &$gagnant) {
            $pions = $partie->pions;
            $pions[$partie->cleDe($joueurId)] = ['row' => $row, 'col' => $col];
            $partie->forceFill(['pions' => $pions])->save();

            $ligneArrivee = $partie->ligneArriveeDe($partie->cleDe($joueurId));

            if ($row === $ligneArrivee) {
                $gagnant = $joueurId === $partie->joueur1_id ? $partie->joueur1 : $partie->joueur2;
                $partie->forceFill([
                    'statut' => QuoridorPartie::STATUT_TERMINEE,
                    'vainqueur_id' => $joueurId,
                    'tour_id' => null,
                ])->save();

                Point::add($gagnant, $partie->couple, 25, 'Victoire au Quoridor', 'quoridor');
                RecompenseService::check($partie->couple);

                return;
            }

            $partie->forceFill([
                'tour_id' => $partie->opposantDe($partie->tour)->id,
            ])->save();
        });

        return [
            'message' => $gagnant !== null ? '🏆 '.$gagnant->name.' remporte la partie !' : 'Pion déplacé.',
            'terminee' => $gagnant !== null,
            'vainqueur' => $gagnant?->id,
        ];
    }

    /**
     * Pose un mur pour le joueur courant, en gardant toujours un chemin aux deux pions.
     *
     * @return array{message: string, terminee: bool, vainqueur: ?int}
     */
    public function poser(QuoridorPartie $partie, int $r, int $c, string $o): array
    {
        $joueurId = $partie->tour_id;
        $cle = $partie->cleDe($joueurId);
        $dejaPoses = collect($this->murs($partie))->filter(fn (array $mur) => $mur['who'] === $cle)->count();

        if ($dejaPoses >= self::MURS_PAR_JOUEUR) {
            abort(422, 'Tu n\'as plus de murs à poser.');
        }

        if (! in_array($o, ['h', 'v'], true)) {
            abort(422, 'Orientation de mur invalide.');
        }

        if (! $this->murValide($partie, $r, $c, $o, $partie->pions)) {
            abort(422, 'Ce mur ne peut pas être posé ici.');
        }

        DB::transaction(function () use ($partie, $r, $c, $o, $cle) {
            $murs = $this->murs($partie);
            $murs[] = ['r' => $r, 'c' => $c, 'o' => $o, 'who' => $cle];
            $partie->forceFill([
                'murs' => array_values($murs),
                'tour_id' => $partie->opposantDe($partie->tour)->id,
            ])->save();
        });

        return ['message' => '🧱 Mur posé.', 'terminee' => false, 'vainqueur' => null];
    }

    public function abandonner(QuoridorPartie $partie, User $joueur): User
    {
        $vainqueur = $partie->opposantDe($joueur);

        DB::transaction(function () use ($partie, $vainqueur) {
            $partie->forceFill([
                'statut' => QuoridorPartie::STATUT_TERMINEE,
                'tour_id' => null,
                'vainqueur_id' => $vainqueur->id,
            ])->save();

            Point::add($vainqueur, $partie->couple, 25, 'Victoire au Quoridor (abandon)', 'quoridor');
            RecompenseService::check($partie->couple);
        });

        return $vainqueur;
    }

    /**
     * État complet de la partie pour le frontal.
     */
    public function etat(QuoridorPartie $partie, User $user): array
    {
        $partie->loadMissing('joueur1', 'joueur2', 'vainqueur');

        $legal = null;
        if ($partie->statut === QuoridorPartie::STATUT_EN_COURS && $partie->tour_id === $user->id) {
            $pion = $partie->pionDe($user->id);
            $legal = [
                'deplacements' => array_values($this->coupsPion($partie, $pion)),
                'murs' => array_values($this->placementsMurs($partie)),
            ];
        }

        return [
            'statut' => $partie->statut,
            'mode' => $partie->mode,
            'tour_id' => $partie->tour_id,
            'joueurs' => [
                'j1' => ['id' => $partie->joueur1->id, 'name' => $partie->joueur1->name, 'couleur' => 'rouge'],
                'j2' => ['id' => $partie->joueur2->id, 'name' => $partie->joueur2->name, 'couleur' => 'bleue'],
            ],
            'pions' => $partie->pions,
            'murs' => array_values($this->murs($partie)),
            'mursRestants' => [
                'j1' => self::MURS_PAR_JOUEUR - collect($this->murs($partie))->filter(fn (array $mur) => $mur['who'] === 'j1')->count(),
                'j2' => self::MURS_PAR_JOUEUR - collect($this->murs($partie))->filter(fn (array $mur) => $mur['who'] === 'j2')->count(),
            ],
            'legal' => $legal,
            'vainqueur_id' => $partie->vainqueur_id,
        ];
    }

    private function coupsPion(QuoridorPartie $partie, array $pion): array
    {
        [$r, $c] = [$pion['row'], $pion['col']];
        $murs = $this->murs($partie);
        $cle = $partie->cleDe($partie->tour_id);
        $adverse = $partie->pions[$cle === 'j1' ? 'j2' : 'j1'];
        $cases = [];

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
            $nr = $r + $dr;
            $nc = $c + $dc;

            if (! $this->dansPlateau($nr, $nc) || ! $this->traversable($murs, [$r, $c], [$nr, $nc])) {
                continue;
            }

            if ($nr !== $adverse['row'] || $nc !== $adverse['col']) {
                $cases[] = ['row' => $nr, 'col' => $nc];

                continue;
            }

            $br = $nr + $dr;
            $bc = $nc + $dc;
            if ($this->dansPlateau($br, $bc) && $this->traversable($murs, [$nr, $nc], [$br, $bc]) && ! $this->occupe($partie, $br, $bc)) {
                $cases[] = ['row' => $br, 'col' => $bc];

                continue;
            }

            foreach ([[$dc, $dr], [-$dc, -$dr]] as [$sd, $sc]) {
                $tr = $nr + $sd;
                $tc = $nc + $sc;
                if ($this->dansPlateau($tr, $tc) && ! $this->occupe($partie, $tr, $tc) && $this->traversable($murs, [$nr, $nc], [$tr, $tc])) {
                    $cases[] = ['row' => $tr, 'col' => $tc];
                }
            }
        }

        return $cases;
    }

    private function placementsMurs(QuoridorPartie $partie): array
    {
        $pions = $partie->pions;
        $cle = $partie->cleDe($partie->tour_id);
        if (collect($this->murs($partie))->filter(fn (array $mur) => $mur['who'] === $cle)->count() >= self::MURS_PAR_JOUEUR) {
            return [];
        }

        $valides = [];

        for ($r = 0; $r < self::TAILLE - 1; $r++) {
            for ($c = 0; $c < self::TAILLE - 1; $c++) {
                if ($this->murValide($partie, $r, $c, 'h', $pions)) {
                    $valides[] = ['r' => $r, 'c' => $c, 'o' => 'h'];
                }
            }
        }

        for ($r = 0; $r < self::TAILLE - 1; $r++) {
            for ($c = 0; $c < self::TAILLE - 1; $c++) {
                if ($this->murValide($partie, $r, $c, 'v', $pions)) {
                    $valides[] = ['r' => $r, 'c' => $c, 'o' => 'v'];
                }
            }
        }

        return $valides;
    }

    private function murValide(QuoridorPartie $partie, int $r, int $c, string $o, array $pions): bool
    {
        $allEdges = [];

        foreach ($this->murs($partie) as $mur) {
            foreach ($this->edifsMur($mur['r'], $mur['c'], $mur['o']) as $arete) {
                $allEdges[$arete] = true;
            }
        }

        foreach ($this->edifsMur($r, $c, $o) as $arete) {
            if (isset($allEdges[$arete])) {
                return false;
            }
        }

        $mursFinaux = $this->murs($partie);
        $mursFinaux[] = ['r' => $r, 'c' => $c, 'o' => $o, 'who' => 'temp'];

        return $this->cheminsOuverts($partie, $pions, $mursFinaux);
    }

    private function murs(QuoridorPartie $partie): array
    {
        return $partie->murs ?? [];
    }

    private function cheminsOuverts(QuoridorPartie $partie, array $pions, array $murs): bool
    {
        return $this->aChemin($pions['j1']['row'], $pions['j1']['col'], $partie->ligneArriveeDe('j1'), $murs)
            && $this->aChemin($pions['j2']['row'], $pions['j2']['col'], $partie->ligneArriveeDe('j2'), $murs);
    }

    private function aChemin(int $r, int $c, int $ligneArrivee, array $murs): bool
    {
        $visites = [];
        $file = [[$r, $c]];
        $visites[$r.','.$c] = true;

        while ($file !== []) {
            [$cr, $cc] = array_shift($file);

            if ($cr === $ligneArrivee) {
                return true;
            }

            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
                $nr = $cr + $dr;
                $nc = $cc + $dc;

                if (! $this->dansPlateau($nr, $nc) || isset($visites[$nr.','.$nc]) || ! $this->traversable($murs, [$cr, $cc], [$nr, $nc])) {
                    continue;
                }

                $visites[$nr.','.$nc] = true;
                $file[] = [$nr, $nc];
            }
        }

        return $r === $ligneArrivee;
    }

    private function traversable(array $murs, array $a, array $b): bool
    {
        [$cle] = $this->areteCanon($a, $b);

        foreach ($murs as $mur) {
            foreach ($this->edifsMur($mur['r'], $mur['c'], $mur['o']) as $arete) {
                if ($arete === $cle) {
                    return false;
                }
            }
        }

        return true;
    }

    private function edifsMur(int $r, int $c, string $o): array
    {
        if ($o === 'h') {
            return [
                $this->areteCanon([$r, $c], [$r + 1, $c])[0],
                $this->areteCanon([$r, $c + 1], [$r + 1, $c + 1])[0],
            ];
        }

        return [
            $this->areteCanon([$r, $c], [$r, $c + 1])[0],
            $this->areteCanon([$r + 1, $c], [$r + 1, $c + 1])[0],
        ];
    }

    private function areteCanon(array $a, array $b): array
    {
        $ordre = [$a, $b];
        usort($ordre, fn ($a, $b) => $a <=> $b);

        return [$ordre[0][0].','.$ordre[0][1].'-'.$ordre[1][0].','.$ordre[1][1]];
    }

    private function dansPlateau(int $r, int $c): bool
    {
        return $r >= 0 && $r < self::TAILLE && $c >= 0 && $c < self::TAILLE;
    }

    private function occupe(QuoridorPartie $partie, int $r, int $c): bool
    {
        foreach ($partie->pions as $p) {
            if ($p['row'] === $r && $p['col'] === $c) {
                return true;
            }
        }

        return false;
    }
}
