<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'titre', 'categorie', 'lieu', 'realise', 'realise_at', 'photos', 'cree_par'])]
class BucketListItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'categorie' => 'string',
            'realise' => 'boolean',
            'realise_at' => 'datetime',
            'photos' => 'array',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
