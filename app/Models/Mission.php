<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['texte', 'difficulte'])]
class Mission extends Model
{
    protected $table = 'missions';

    protected function casts(): array
    {
        return [
            'difficulte' => 'string',
        ];
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(MissionTrack::class);
    }
}
