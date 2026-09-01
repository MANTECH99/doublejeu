<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_question_id', 'joueur_id', 'reponse'])]
class QuizReponse extends Model
{
    use HasFactory;

    protected $table = 'quiz_reponses';

    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizSessionQuestion::class, 'session_question_id');
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joueur_id');
    }
}
