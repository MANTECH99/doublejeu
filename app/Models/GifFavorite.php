<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'gif_url', 'gif_alt'])]
class GifFavorite extends Model
{
    use HasFactory;

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }
}
