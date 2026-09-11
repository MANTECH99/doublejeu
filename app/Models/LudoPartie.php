<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'joueur1_id', 'joueur2_id', 'statut', 'tour_id', 'dernier_de', 'de_passe', 'six_compte', 'vainqueur_id'])]
class LudoPartie extends Model
{
    use HasFactory;

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TERMINEE = 'terminee';

    protected function casts(): array
    {
        return [
            'dernier_de' => 'integer',
            'de_passe' => 'integer',
            'six_compte' => 'integer',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function joueur1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur1_id');
    }

    public function joueur2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur2_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tour_id');
    }

    public function vainqueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vainqueur_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(LudoToken::class, 'partie_id');
    }

    public function tokensOf(int $joueurId): HasMany
    {
        return $this->tokens()->where('joueur_id', $joueurId)->orderBy('numero');
    }

    public function jouePar(User $user): bool
    {
        return $this->joueur1_id === $user->id || $this->joueur2_id === $user->id;
    }

    public function opposantDe(User $user): User
    {
        return $user->id === $this->joueur1_id ? $this->joueur2 : $this->joueur1;
    }
}
