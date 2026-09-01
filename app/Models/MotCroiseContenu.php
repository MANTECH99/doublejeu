<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mot', 'indice', 'created_by'])]
class MotCroiseContenu extends Model
{
    use HasFactory;

    protected $table = 'mots_croises_contenu';
}
