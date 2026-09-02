<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'email', 'password', 'gender', 'avatar_url', 'couple_id', 'date_naissance', 'devin_mission_jour', 'devin_mission_reponse', 'devin_mission_resultat', 'devin_mission_compteur', 'typing_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'devin_mission_jour' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'typing_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function coupleModel(): BelongsTo
    {
        return $this->belongsTo(Couple::class, 'couple_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function getPartnerAttribute(): ?User
    {
        return $this->coupleModel?->partnerOf($this);
    }

    public function avatarInitial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function avatarColor(): string
    {
        $colors = ['#E63946', '#FF6B6B', '#F06595', '#E64980'];
        $sum = 0;
        foreach (str_split($this->name) as $char) {
            $sum += ord($char);
        }

        return $colors[$sum % count($colors)];
    }

    public function hasPhoto(): bool
    {
        return ! empty($this->avatar_url);
    }

    public function photoUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        return asset('storage/'.$this->avatar_url);
    }

    public function prochainAnniversaire(): ?Carbon
    {
        if (! $this->date_naissance) {
            return null;
        }

        $prochain = today()->setMonth($this->date_naissance->month)
            ->setDay($this->date_naissance->day)
            ->startOfDay();

        if ($prochain->lt(today()->startOfDay())) {
            $prochain = $prochain->addYear();
        }

        return $prochain;
    }
}
