<?php

namespace App\Services;

class MotsCroisesGenerator
{
    public const TAILLE = 15;

    /**
     * Place les mots donnés (avec indice + réponse) dans une grille de mots croisés.
     *
     * @param  array<int, array{mot:string, indice:string}>  $mots
     * @return array{lignes:int, colonnes:int, cases:array<string,string>, mots:array<int, array{numero:int, mot:string, indice:string, orientation:string, position:array{0:int,1:int}}>}|null
     */
    public static function generer(array $mots): ?array
    {
        $mots = array_values(array_filter($mots, fn ($m) => trim((string) ($m['mot'] ?? '')) !== ''));

        if (count($mots) < 2) {
            return null;
        }

        // Tries par longueur décroissante : le plus long devient le cœur de la grille.
        usort($mots, fn ($a, $b) => mb_strlen($b['mot']) <=> mb_strlen($a['mot']));

        $best = null;
        for ($essai = 0; $essai < 40; $essai++) {
            $tentative = self::tenter($mots);
            if ($tentative === null) {
                continue;
            }
            if (
                $best === null
                || count($tentative['mots']) > count($best['mots'])
                || (count($tentative['mots']) === count($best['mots']) && $tentative['lignes'] * $tentative['colonnes'] < $best['lignes'] * $best['colonnes'])
            ) {
                $best = $tentative;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array{mot:string, indice:string}>  $mots
     * @return array{lignes:int, colonnes:int, cases:array<string,string>, mots:array<int, array{numero:int, mot:string, indice:string, orientation:string, position:array{0:int,1:int}}>}|null
     */
    private static function tenter(array $mots): ?array
    {
        $taille = self::TAILLE;
        $grille = array_fill(0, $taille, array_fill(0, $taille, null));

        $deck = $mots;
        shuffle($deck);

        // Le mot le plus long sert de « fil rouge » horizontal.
        $seed = array_shift($deck);
        $gridWords = [self::poser(
            $grille,
            $seed['mot'],
            ['indice' => $seed['indice'], 'orientation' => 'h', 'position' => [intdiv($taille, 2), intdiv($taille - mb_strlen($seed['mot']), 2)]]
        )];

        $place = true;
        while ($place && count($deck) > 0) {
            $place = false;
            foreach ($deck as $i => $word) {
                $pose = self::essayerPlacement($grille, $word['mot'], $gridWords);
                if ($pose !== null) {
                    $grille = $pose['grille'];
                    $gridWords[] = self::poser($grille, $word['mot'], [
                        'indice' => $word['indice'],
                        'orientation' => $pose['orientation'],
                        'position' => [$pose['r'], $pose['c']],
                    ]);
                    unset($deck[$i]);
                    $deck = array_values($deck);
                    $place = true;
                    break;
                }
            }
        }

        if (count($gridWords) < 3) {
            return null;
        }

        return self::finaliser($grille, $gridWords);
    }

    /**
     * Trouve une position où le mot peut être posé en croisant un mot existant.
     *
     * @param  array<int, array<int, string|null>>  $grille
     * @param  array<int, array{mot:string, indice:string, orientation:string, position:array{0:int,1:int}}>  $poses
     * @return array{grille:array, r:int, c:int, orientation:string}|null
     */
    private static function essayerPlacement(array &$grille, string $mot, array $poses): ?array
    {
        $taille = count($grille);

        foreach ($poses as $pose) {
            $p = $pose['position'];
            $letters = mb_str_split($pose['mot']);

            if ($pose['orientation'] === 'h') {
                // Le nouveau mot vertical croise le mot horizontal posé.
                foreach (self::casesDuMot($pose) as $k => $pos) {
                    $rr = $pos[0];
                    $cc = $pos[1];
                    $lettre = $letters[$k];
                    $occ = self::occurrences($mot, $lettre);
                    if ($occ === null) {
                        continue;
                    }
                    shuffle($occ);
                    foreach ($occ as $j) {
                        // Lettre existante (rr,cc) = lettre index $j du nouveau mot vertical.
                        if (self::peutPoser($grille, $mot, $rr - $j, $cc, 'v', $rr, $cc)) {
                            return ['grille' => $grille, 'r' => $rr - $j, 'c' => $cc, 'orientation' => 'v'];
                        }
                    }
                }
            } else {
                // Le nouveau mot horizontal croise le mot vertical posé.
                foreach (self::casesDuMot($pose) as $k => $pos) {
                    $rr = $pos[0];
                    $cc = $pos[1];
                    $lettre = $letters[$k];
                    $occ = self::occurrences($mot, $lettre);
                    if ($occ === null) {
                        continue;
                    }
                    shuffle($occ);
                    foreach ($occ as $j) {
                        // Lettre existante (rr,cc) = lettre index $j du nouveau mot horizontal.
                        if (self::peutPoser($grille, $mot, $rr, $cc - $j, 'h', $rr, $cc)) {
                            return ['grille' => $grille, 'r' => $rr, 'c' => $cc - $j, 'orientation' => 'h'];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Indices (r,c) occupés par un mot déjà posé (h|v).
     *
     * @param  array{mot:string, orientation:string, position:array{0:int,1:int}}  $w
     * @return array<int, array{0:int,1:int}>
     */
    private static function casesDuMot(array $w): array
    {
        $long = mb_strlen($w['mot']);
        $out = [];
        [$r, $c] = $w['position'];
        for ($k = 0; $k < $long; $k++) {
            $out[] = $w['orientation'] === 'h' ? [$r, $c + $k] : [$r + $k, $c];
        }

        return $out;
    }

    /**
     * Vérifie que $mot peut être placé à partir de (r,c) en orientation $orientation,
     * en croisant au moins une lettre existante à (crossR,crossC), et le marque.
     *
     * @param  array<int, array<int, string|null>>  $grille
     */
    private static function peutPoser(array &$grille, string $mot, int $r, int $c, string $orientation, int $crossR, int $crossC): bool
    {
        $taille = count($grille);
        $long = mb_strlen($mot);

        if ($r < 0 || $c < 0) {
            return false;
        }
        if ($orientation === 'h' && $c + $long > $taille) {
            return false;
        }
        if ($orientation === 'v' && $r + $long > $taille) {
            return false;
        }

        $croise = false;

        for ($k = 0; $k < $long; $k++) {
            $rr = $orientation === 'h' ? $r : $r + $k;
            $cc = $orientation === 'h' ? $c + $k : $c;
            $existing = $grille[$rr][$cc] ?? null;
            $lettre = mb_substr($mot, $k, 1);

            if ($existing !== null) {
                if ($existing !== $lettre) {
                    return false;
                }
                if ($rr === $crossR && $cc === $crossC) {
                    $croise = true;
                }

                continue;
            }

            // Aucune lettre collée au chemin du mot, sauf la case croisée
            // (l'intersection avec le mot porteur).
            if ($rr === $crossR && $cc === $crossC) {
                continue;
            }
            if ($orientation === 'h') {
                // Seuls les voisins verticaux gênent un mot horizontal.
                if (($grille[$rr - 1][$cc] ?? null) !== null) {
                    return false;
                }
                if (($grille[$rr + 1][$cc] ?? null) !== null) {
                    return false;
                }
            } else {
                // Seuls les voisins horizontaux gênent un mot vertical.
                if (($grille[$rr][$cc - 1] ?? null) !== null) {
                    return false;
                }
                if (($grille[$rr][$cc + 1] ?? null) !== null) {
                    return false;
                }
            }
        }

        if (! $croise) {
            return false;
        }

        // Pas de lettre directement « collée » avant ou après le mot.
        $avant = $orientation === 'h' ? ($grille[$r][$c - 1] ?? null) : ($grille[$r - 1][$c] ?? null);
        $apres = $orientation === 'h' ? ($grille[$r][$c + $long] ?? null) : ($grille[$r + $long][$c] ?? null);
        if ($avant !== null || $apres !== null) {
            return false;
        }

        for ($k = 0; $k < $long; $k++) {
            $rr = $orientation === 'h' ? $r : $r + $k;
            $cc = $orientation === 'h' ? $c + $k : $c;
            if (($grille[$rr][$cc] ?? null) === null) {
                $grille[$rr][$cc] = mb_substr($mot, $k, 1);
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, string|null>>  $grille
     * @return array{mot:string, indice:string, orientation:string, position:array{0:int,1:int}}
     */
    private static function poser(array &$grille, string $mot, array $meta): array
    {
        $long = mb_strlen($mot);
        [$r, $c] = $meta['position'];
        for ($k = 0; $k < $long; $k++) {
            $rr = $meta['orientation'] === 'h' ? $r : $r + $k;
            $cc = $meta['orientation'] === 'h' ? $c + $k : $c;
            if (($grille[$rr][$cc] ?? null) === null) {
                $grille[$rr][$cc] = mb_substr($mot, $k, 1);
            }
        }

        return [
            'mot' => $mot,
            'indice' => $meta['indice'],
            'orientation' => $meta['orientation'],
            'position' => [$r, $c],
        ];
    }

    /**
     * Position des lettres de $mot identiques à $lettre.
     */
    private static function occurrences(string $mot, string $lettre): ?array
    {
        $out = [];
        foreach (mb_str_split($mot) as $i => $l) {
            if ($l === $lettre) {
                $out[] = $i;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<int, array<int, string|null>>  $grille
     * @param  array<int, array{mot:string, indice:string, orientation:string, position:array{0:int,1:int}}>  $words
     * @return array{lignes:int, colonnes:int, cases:array<string,string>, mots:array<int, array{numero:int, mot:string, indice:string, orientation:string, position:array{0:int,1:int}}>}
     */
    private static function finaliser(array $grille, array $words): array
    {
        $taille = count($grille);

        $minR = $taille;
        $maxR = -1;
        $minC = $taille;
        $maxC = -1;

        foreach ($words as $w) {
            $long = mb_strlen($w['mot']);
            [$r, $c] = $w['position'];
            if ($w['orientation'] === 'h') {
                $minR = min($minR, $r);
                $maxR = max($maxR, $r);
                $minC = min($minC, $c);
                $maxC = max($maxC, $c + $long - 1);
            } else {
                $minR = min($minR, $r);
                $maxR = max($maxR, $r + $long - 1);
                $minC = min($minC, $c);
                $maxC = max($maxC, $c);
            }
        }

        $lignes = $maxR - $minR + 1;
        $colonnes = $maxC - $minC + 1;

        $cases = [];
        for ($rr = 0; $rr < $lignes; $rr++) {
            for ($cc = 0; $cc < $colonnes; $cc++) {
                $lettre = $grille[$minR + $rr][$minC + $cc] ?? null;
                $cases["{$rr},{$cc}"] = $lettre ?? '';
            }
        }

        $numbered = [];
        foreach ($words as $i => $w) {
            $numbered[] = [
                'numero' => $i + 1,
                'mot' => $w['mot'],
                'indice' => $w['indice'],
                'orientation' => $w['orientation'],
                'position' => [$w['position'][0] - $minR, $w['position'][1] - $minC],
            ];
        }

        return [
            'lignes' => $lignes,
            'colonnes' => $colonnes,
            'cases' => $cases,
            'mots' => $numbered,
        ];
    }
}
