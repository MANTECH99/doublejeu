<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'partie_id', 'question_id', 'statut', 'realisee_at'])]
class MissionOuiNon extends Model
{
    use HasFactory;

    protected $table = 'missions_oui_non';

    protected function casts(): array
    {
        return [
            'statut' => 'string',
            'realisee_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionOuiNon::class);
    }
}
