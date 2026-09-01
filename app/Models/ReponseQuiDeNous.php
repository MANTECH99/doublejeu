<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partie_question_id', 'joueur_id', 'designation'])]
class ReponseQuiDeNous extends Model
{
    use HasFactory;

    protected $table = 'reponses_qui_de_nous';

    protected function casts(): array
    {
        return [
            'designation' => 'string',
        ];
    }

    public function partieQuestion(): BelongsTo
    {
        return $this->belongsTo(PartieQuestionQuiDeNous::class, 'partie_question_id');
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_id');
    }
}
