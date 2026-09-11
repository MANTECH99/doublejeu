<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partie_id', 'joueur_id', 'couleur', 'numero', 'position'])]
class LudoToken extends Model
{
    use HasFactory;

    public const POSITION_BASE = -1;

    public const COULEUR_ROUGE = 'rouge';

    public const COULEUR_BLEUE = 'bleue';

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'position' => 'integer',
        ];
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(LudoPartie::class, 'partie_id');
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_id');
    }

    public function estArrive(): bool
    {
        return $this->position >= LudoService::FINISH;
    }
}
