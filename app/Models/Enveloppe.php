<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'joueur_id', 'couleur', 'defi_id', 'statut', 'partie_joueur_qui_realise', 'accepte'])]
class Enveloppe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'statut' => 'string',
            'accepte' => 'boolean',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defi(): BelongsTo
    {
        return $this->belongsTo(DefiEnveloppe::class);
    }
}
