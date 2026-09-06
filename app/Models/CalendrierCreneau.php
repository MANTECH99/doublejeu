<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'user_id', 'date_jour', 'titre', 'raison', 'heure_debut', 'heure_fin', 'couleur'])]
class CalendrierCreneau extends Model
{
    use HasFactory;

    protected $table = 'calendrier_creneaux';

    protected function casts(): array
    {
        return [
            'date_jour' => 'string',
            'raison' => 'string',
            'heure_debut' => 'string',
            'heure_fin' => 'string',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
