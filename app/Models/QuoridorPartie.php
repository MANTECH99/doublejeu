<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'joueur1_id', 'joueur2_id', 'statut', 'mode', 'tour_id', 'pions', 'murs', 'vainqueur_id'])]
class QuoridorPartie extends Model
{
    use HasFactory;

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TERMINEE = 'terminee';

    public const MODE_CLASSIQUE = 'classique';

    public const MODE_COURSE = 'course';

    public const TAILLE = 9;

    public const MURS_PAR_JOUEUR = 10;

    public const LIGNE_ARRIVEE_J1 = 0;

    public const LIGNE_ARRIVEE_J2 = 8;

    protected function casts(): array
    {
        return [
            'pions' => 'array',
            'murs' => 'array',
        ];
    }

    public function ligneArriveeDe(string $cle): int
    {
        if ($this->mode === self::MODE_COURSE) {
            return self::LIGNE_ARRIVEE_J1;
        }

        return $cle === 'j1' ? self::LIGNE_ARRIVEE_J1 : self::LIGNE_ARRIVEE_J2;
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

    public function cleDe(int $joueurId): string
    {
        return $joueurId === $this->joueur1_id ? 'j1' : 'j2';
    }

    public function pionDe(int $joueurId): array
    {
        return $this->pions[$this->cleDe($joueurId)];
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
