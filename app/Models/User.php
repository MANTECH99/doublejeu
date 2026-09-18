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
use LaravelWebauthn\WebauthnAuthenticatable;

#[Fillable(['name', 'email', 'password', 'gender', 'avatar_url', 'couple_id', 'date_naissance', 'devin_mission_jour', 'devin_mission_reponse', 'devin_mission_resultat', 'devin_mission_compteur', 'timezone', 'mission_question_notif_jour', 'devin_verdict_vu_jour', 'typing_at', 'recording_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, WebauthnAuthenticatable;

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'devin_mission_jour' => 'datetime',
            'mission_question_notif_jour' => 'datetime',
            'devin_verdict_vu_jour' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'typing_at' => 'datetime',
            'recording_at' => 'datetime',
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

    public function localTimezone(): string
    {
        return $this->timezone ?: config('app.timezone', 'UTC');
    }

    public function localNow(): Carbon
    {
        return now()->timezone($this->localTimezone());
    }

    public function localToday(): Carbon
    {
        return $this->localNow()->copy()->startOfDay();
    }

    public function deadlineSoir(): Carbon
    {
        return $this->localToday()->setTime(20, 0);
    }

    public function deadlineSoirPassee(): bool
    {
        return now()->gte($this->deadlineSoir()->setTimezone(config('app.timezone', 'UTC')));
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
