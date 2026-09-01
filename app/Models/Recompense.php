<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'joueur_gagnant_id', 'joueur_perdant_id', 'texte', 'statut'])]
class Recompense extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'statut' => 'string',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function gagnant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_gagnant_id');
    }

    public function perdant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_perdant_id');
    }
}
