<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'createur_id', 'semaine', 'statut', 'mots', 'grille', 'reponses_user1', 'reponses_user2', 'attribues_user1', 'attribues_user2', 'proposition_user1', 'proposition_user2'])]
class GrilleMotsCroises extends Model
{
    use HasFactory;

    protected $table = 'grilles_mots_croises';

    protected function casts(): array
    {
        return [
            'semaine' => 'date',
            'mots' => 'array',
            'grille' => 'array',
            'reponses_user1' => 'array',
            'reponses_user2' => 'array',
            'attribues_user1' => 'array',
            'attribues_user2' => 'array',
            'proposition_user1' => 'array',
            'proposition_user2' => 'array',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** Créateur de la grille (celui qui a inventé les mots pour l'autre). */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public static function pourCreateur(Couple $couple, int $createurId): ?self
    {
        return self::where('couple_id', $couple->id)
            ->where('createur_id', $createurId)
            ->first();
    }

    /** Réponses (r,c => lettre) du joueur donné, en tableau. */
    public function reponsesPour(int $userId): array
    {
        return $this->couple->user1_id === $userId ? ($this->reponses_user1 ?? []) : ($this->reponses_user2 ?? []);
    }

    /** Coordonnées (r,c) déjà valorisées pour le joueur donné. */
    public function attribuesPour(int $userId): array
    {
        return $this->couple->user1_id === $userId ? ($this->attribues_user1 ?? []) : ($this->attribues_user2 ?? []);
    }

    /** Brouillon (lettres en cours de saisie, même fausses) du joueur donné. */
    public function brouillonsPour(int $userId): array
    {
        return $this->couple->user1_id === $userId ? ($this->proposition_user1 ?? []) : ($this->proposition_user2 ?? []);
    }

    public function estComplete(): bool
    {
        if (! $this->grille || ! $this->createur) {
            return false;
        }

        $cases = $this->grille['cases'] ?? [];
        $valid1 = $this->reponsesPour($this->couple->user1_id ?? 0);
        $valid2 = $this->reponsesPour($this->couple->user2_id ?? 0);

        foreach ($cases as $cle => $valeur) {
            // On ne traite que les cases à remplir (solution non vide).
            if ($valeur === '') {
                continue;
            }
            if (($valid1[$cle] ?? '') === '' && ($valid2[$cle] ?? '') === '') {
                return false;
            }
        }

        return true;
    }
}
