<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'joueur1_id', 'joueur2_id', 'status', 'score_joueur1', 'score_joueur2'])]
class PartieOuiNon extends Model
{
    use HasFactory;

    protected $table = 'parties_oui_non';

    protected function casts(): array
    {
        return [
            'status' => 'string',
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

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseOuiNon::class, 'partie_id');
    }
}
