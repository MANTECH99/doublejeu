<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partie_id', 'joueur_id', 'type', 'carte_id', 'reponse', 'piece_jointe', 'accepte', 'points_gagnes', 'statut'])]
class TourVO extends Model
{
    use HasFactory;

    protected $table = 'tours_vo';

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'accepte' => 'boolean',
            'statut' => 'string',
        ];
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(PartieVO::class);
    }

    public function joueur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carteTexte(): string
    {
        if ($this->type === 'verite') {
            return CarteVerite::find($this->carte_id)?->texte ?? '—';
        }

        if ($this->type === 'action') {
            return CarteAction::find($this->carte_id)?->texte ?? '—';
        }

        return Gage::find($this->carte_id)?->texte ?? '—';
    }
}
