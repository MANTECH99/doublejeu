<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\Point;
use App\Models\Recompense;
use Illuminate\Support\Facades\DB;

class RecompenseService
{
    public const SEUILS = [
        100 => 'Un massage',
        250 => 'Un dîner surprise',
        500 => 'Exaucer un souhait',
        1000 => 'Récompense personnalisée',
    ];

    public static function check(Couple $couple): array
    {
        $created = [];

        if (! $couple->isLinked()) {
            return $created;
        }

        $dominant = self::dominantPlayer($couple);
        if (! $dominant) {
            return $created;
        }

        $perdant = $couple->partnerOf($dominant);

        foreach (self::SEUILS as $seuil => $texte) {
            if ($couple->score_total < $seuil) {
                continue;
            }

            $exists = Recompense::where('couple_id', $couple->id)
                ->where('texte', $texte)
                ->exists();

            if ($exists) {
                continue;
            }

            $created[] = Recompense::create([
                'couple_id' => $couple->id,
                'joueur_gagnant_id' => $dominant->id,
                'joueur_perdant_id' => $perdant->id,
                'texte' => $texte,
                'statut' => 'due',
            ]);
        }

        return $created;
    }

    public static function dominantPlayer(Couple $couple)
    {
        $scores = Point::where('couple_id', $couple->id)
            ->select('joueur_id', DB::raw('SUM(montant) total'))
            ->groupBy('joueur_id')
            ->orderByDesc('total')
            ->pluck('total', 'joueur_id');

        if ($scores->isEmpty()) {
            return $couple->user1;
        }

        $bestId = (int) $scores->keys()->first();

        return $bestId === $couple->user1?->id
            ? $couple->user1
            : $couple->user2 ?? $couple->user1;
    }

    public static function personalScore(Couple $couple, int $userId): int
    {
        return (int) Point::where('couple_id', $couple->id)
            ->where('joueur_id', $userId)
            ->sum('montant');
    }
}
