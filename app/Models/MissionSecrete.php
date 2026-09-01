<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'joueur_cible_id', 'texte', 'difficulte', 'statut', 'devine', 'revele_at', 'date_debut', 'date_fin', 'accomplie_at'])]
class MissionSecrete extends Model
{
    use HasFactory;

    protected $table = 'missions_secretes';

    protected function casts(): array
    {
        return [
            'statut' => 'string',
            'revele_at' => 'datetime',
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'accomplie_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function joueurCible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_cible_id');
    }

    public function scopeForCouple($q, int $coupleId): mixed
    {
        return $q->where('couple_id', $coupleId);
    }
}
