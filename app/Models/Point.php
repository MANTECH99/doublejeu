<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'joueur_id', 'montant', 'raison', 'source'])]
class Point extends Model
{
    use HasFactory;

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function add(User $joueur, Couple $couple, int $montant, string $raison, ?string $source = null): Point
    {
        $point = static::create([
            'couple_id' => $couple->id,
            'joueur_id' => $joueur->id,
            'montant' => $montant,
            'raison' => $raison,
            'source' => $source,
        ]);

        $couple->score_total = max(0, $couple->score_total + $montant);
        $couple->save();

        return $point;
    }
}
