<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['texte', 'couleur', 'created_by'])]
class DefiEnveloppe extends Model
{
    use HasFactory;

    protected $table = 'defis_enveloppes';
}
