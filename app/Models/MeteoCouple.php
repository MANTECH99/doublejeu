<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['couple_id', 'jour', 'humeur_user1', 'commentaire_user1', 'humeur_user2', 'commentaire_user2', 'suggestion_user1', 'suggestion_user2'])]
class MeteoCouple extends Model
{
    use HasFactory;

    protected $table = 'meteo_couples';

    public const METEOS = [
        'heureux' => ['label' => 'Heureux', 'emoji' => '😊', 'niveau' => 'bon'],
        'amoureux' => ['label' => 'Amoureux', 'emoji' => '🥰', 'niveau' => 'bon'],
        'calme' => ['label' => 'Calme', 'emoji' => '😌', 'niveau' => 'mitige'],
        'fatigue' => ['label' => 'Fatigué', 'emoji' => '😴', 'niveau' => 'mitige'],
        'stress' => ['label' => 'Stressé', 'emoji' => '😰', 'niveau' => 'mauvais'],
        'triste' => ['label' => 'Triste', 'emoji' => '😢', 'niveau' => 'mauvais'],
        'ennui' => ['label' => 'Ennuyé', 'emoji' => '😑', 'niveau' => 'mauvais'],
        'colere' => ['label' => 'En colère', 'emoji' => '😠', 'niveau' => 'mauvais'],
    ];

    protected function casts(): array
    {
        return [
            'jour' => 'date',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public static function aujourdhuiPour(Couple $couple): ?self
    {
        return self::where('couple_id', $couple->id)
            ->whereDate('jour', today())
            ->first();
    }

    public static function niveau(string $humeur): ?string
    {
        return static::METEOS[$humeur]['niveau'] ?? null;
    }

    public function humeurPour(int $userId): ?string
    {
        return $this->couple->user1_id === $userId ? $this->humeur_user1 : $this->humeur_user2;
    }

    public function commentairePour(int $userId): ?string
    {
        return $this->couple->user1_id === $userId ? $this->commentaire_user1 : $this->commentaire_user2;
    }

    public function suggestionPour(int $userId): ?string
    {
        return $this->couple->user1_id === $userId ? $this->suggestion_user1 : $this->suggestion_user2;
    }

    public function estComplet(): bool
    {
        return ! empty($this->humeur_user1) && ! empty($this->humeur_user2);
    }

    public function lesDeuxMauvais(): bool
    {
        if (! $this->estComplet()) {
            return false;
        }

        return static::niveau($this->humeur_user1) === 'mauvais'
            && static::niveau($this->humeur_user2) === 'mauvais';
    }

    public static function synthese(?string $ma, ?string $sa): ?array
    {
        if (! $ma || ! $sa) {
            return null;
        }

        $na = static::niveau($ma);
        $nb = static::niveau($sa);

        if ($na === 'bon' && $nb === 'bon') {
            return ['emoji' => '🌈', 'label' => 'Le ciel est dégagé !'];
        }

        if ($na === 'mauvais' && $nb === 'mauvais') {
            return ['emoji' => '🌩️', 'label' => 'Tempête dans le couple… prenez soin de vous'];
        }

        if ($na === 'mauvais' || $nb === 'mauvais') {
            return ['emoji' => '🌦️', 'label' => 'Quelques nuages, mais rien d\'insurmontable'];
        }

        return ['emoji' => '⛅', 'label' => 'Un ciel partagé, mais au chaud ensemble'];
    }
}
