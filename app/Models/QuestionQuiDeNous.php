<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['texte', 'categorie', 'created_by'])]
class QuestionQuiDeNous extends Model
{
    use HasFactory;

    protected $table = 'questions_qui_de_nous';

    protected function casts(): array
    {
        return [
            'categorie' => 'string',
        ];
    }
}
