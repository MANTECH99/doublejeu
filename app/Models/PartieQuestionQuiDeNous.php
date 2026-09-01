<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['partie_id', 'question_id', 'ordre', 'resultat', 'debat_resolu'])]
class PartieQuestionQuiDeNous extends Model
{
    use HasFactory;

    protected $table = 'parties_qui_de_nous_questions';

    protected function casts(): array
    {
        return [
            'resultat' => 'string',
            'debat_resolu' => 'boolean',
        ];
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(PartieQuiDeNous::class, 'partie_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionQuiDeNous::class, 'question_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseQuiDeNous::class, 'partie_question_id');
    }
}
