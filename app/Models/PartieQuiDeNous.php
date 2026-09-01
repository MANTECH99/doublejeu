<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'joueur1_id', 'joueur2_id', 'statut'])]
class PartieQuiDeNous extends Model
{
    use HasFactory;

    protected $table = 'parties_qui_de_nous';

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

    public function joueur1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur1_id');
    }

    public function joueur2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur2_id');
    }

    public function partieQuestions(): HasMany
    {
        return $this->hasMany(PartieQuestionQuiDeNous::class, 'partie_id')->orderBy('ordre');
    }
}
