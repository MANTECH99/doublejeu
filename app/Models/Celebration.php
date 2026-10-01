<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['couple_id', 'auteur_id', 'destinataire_id', 'annee', 'message', 'audio_path', 'video_path', 'activite', 'promesse'])]
class Celebration extends Model
{
    use HasFactory;

    /**
     * Fenêtre de préparation : on peut écrire un cadeau dès 7 jours avant
     * l'anniversaire, jusqu'au jour J inclus.
     */
    public const FENETRE_JOURS = 7;

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    /**
     * La préparation d'un auteur pour le prochain anniversaire d'un destinataire.
     */
    public static function de(User $auteur, User $destinataire): ?self
    {
        $annee = $destinataire->prochainAnniversaire()?->year;

        if ($annee === null) {
            return null;
        }

        return static::where('auteur_id', $auteur->id)
            ->where('destinataire_id', $destinataire->id)
            ->where('annee', $annee)
            ->first();
    }

    /**
     * Fenêtre de préparation ouverte pour l'anniversaire qui tombe dans
     * $joursAvant jours ? Vrai de 7 jours avant jusqu'au jour J inclus.
     */
    public static function fenetreOuverte(?int $joursAvant): bool
    {
        return $joursAvant !== null && $joursAvant >= 0 && $joursAvant <= self::FENETRE_JOURS;
    }

    /**
     * Le cadeau écrit par $auteur pour l'anniversaire de $destinataire, une fois
     * cet anniversaire arrivé (jour J ou après). Null avant : le cadeau reste secret.
     *
     * On regarde l'année en cours et la suivante : un cadeau préparé en décembre
     * pour le 1er janvier cible l'année d'après.
     */
    public static function revelable(User $auteur, User $destinataire): ?self
    {
        if (! $destinataire->date_naissance) {
            return null;
        }

        $aujourdhui = today()->startOfDay();

        return static::where('auteur_id', $auteur->id)
            ->where('destinataire_id', $destinataire->id)
            ->whereIn('annee', [$aujourdhui->year, $aujourdhui->year + 1])
            ->get()
            ->first(fn (self $c) => $destinataire->date_naissance->copy()
                ->setYear($c->annee)
                ->startOfDay()
                ->lte($aujourdhui));
    }

    public function aUnSon(): bool
    {
        return filled($this->audio_path);
    }

    public function aUneVideo(): bool
    {
        return filled($this->video_path);
    }

    /**
     * URL racine-relative du son (comme les audios de discussion) : le son se
     * charge toujours depuis le domaine qui sert la page, même via une IP LAN.
     */
    public function sonUrl(): ?string
    {
        if (blank($this->audio_path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($this->audio_path), PHP_URL_PATH) ?: null;
    }

    public function videoUrl(): ?string
    {
        if (blank($this->video_path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($this->video_path), PHP_URL_PATH) ?: null;
    }
}
