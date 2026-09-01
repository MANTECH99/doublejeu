<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partie_id', 'question_id', 'joueur_id', 'reponse'])]
class ReponseOuiNon extends Model
{
    use HasFactory;

    protected $table = 'reponses_oui_non';

    protected function casts(): array
    {
        return [
            'reponse' => 'string',
        ];
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(PartieOuiNon::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionOuiNon::class);
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
