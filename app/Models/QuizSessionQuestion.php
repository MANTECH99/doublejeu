<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['session_id', 'question_id', 'cible_id', 'ordre', 'resultat', 'bonne_reponse'])]
class QuizSessionQuestion extends Model
{
    use HasFactory;

    protected $table = 'quiz_session_questions';

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionQuiz::class, 'question_id');
    }

    public function cible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cible_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(QuizReponse::class, 'session_question_id');
    }
}
