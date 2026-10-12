<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['url_hash', 'url', 'title', 'description', 'site', 'image_path', 'fetched_at'])]
class LinkPreview extends Model
{
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }
}
