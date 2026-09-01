<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'niveau', 'status', 'joueur_actif_id', 'score_joueur1', 'score_joueur2'])]
class PartieVO extends Model
{
    use HasFactory;

    protected $table = 'parties_vo';

    protected function casts(): array
    {
        return [
            'niveau' => 'string',
            'status' => 'string',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function joueurActif(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_actif_id');
    }

    public function tours(): HasMany
    {
        return $this->hasMany(TourVO::class, 'partie_id');
    }

    public function scoreFor(User $user): int
    {
        $couple = $this->couple;
        if ($couple->user1_id === $user->id) {
            return $this->score_joueur1;
        }

        return $this->score_joueur2;
    }
}
