<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_journaliere_id', 'joueur_id', 'reponse'])]
class ReponseQuestionJournaliere extends Model
{
    use HasFactory;

    protected $table = 'reponses_questions_journalieres';

    public function questionJournaliere(): BelongsTo
    {
        return $this->belongsTo(QuestionJournaliere::class, 'question_journaliere_id');
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_id');
    }
}
