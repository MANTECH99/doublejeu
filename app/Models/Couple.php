<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_unique', 'user1_id', 'user2_id', 'streak', 'score_total', 'last_activity_at'])]
class Couple extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        do {
            $code = 'DJ-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 5));
        } while (static::where('code_unique', $code)->exists());

        return $code;
    }

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'couple_id');
    }

    public function partnerOf(User $user): ?User
    {
        if ($this->user1_id === $user->id) {
            return $this->user2;
        }

        return $this->user1;
    }

    public function isLinked(): bool
    {
        return ! is_null($this->user1_id) && ! is_null($this->user2_id);
    }

    public function partiesVo(): HasMany
    {
        return $this->hasMany(PartieVO::class);
    }

    public function partiesOuiNon(): HasMany
    {
        return $this->hasMany(PartieOuiNon::class);
    }

    public function missionsSecrettes(): HasMany
    {
        return $this->hasMany(MissionSecrete::class);
    }

    public function missionsSecreteEnCours(): HasMany
    {
        return $this->missionsSecrettes()
            ->whereIn('statut', ['en_attente', 'en_cours']);
    }

    public function missionsOuiNon(): HasMany
    {
        return $this->hasMany(MissionOuiNon::class);
    }

    public function enveloppes(): HasMany
    {
        return $this->hasMany(Enveloppe::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(Point::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
