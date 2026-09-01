<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['texte_soi', 'texte_partenaire', 'categorie', 'created_by'])]
class QuestionQuiz extends Model
{
    use HasFactory;

    protected $table = 'questions_quiz';
}
