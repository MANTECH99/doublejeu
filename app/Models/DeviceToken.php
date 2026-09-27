<?php

namespace App\Models;

use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /** @var list<string> */
    protected $hidden = ['token'];

    /**
     * Les colonnes de dates sont des instances Carbon.
     *
     * @return list<string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Le compte propriétaire de cet appareil de confiance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crée un jeton d'appareil : il identifie le compte sans que l'email soit ressaisi.
     */
    public static function issue(User $user, string $name): self
    {
        return $user->deviceTokens()->forceCreate([
            'token' => Str::random(64),
            'name' => $name !== '' ? $name : 'Cet appareil',
        ]);
    }

    /**
     * Mémorise la dernière utilisation, pour que le profil puisse proposer de révoquer les appareils oubliés.
     */
    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
