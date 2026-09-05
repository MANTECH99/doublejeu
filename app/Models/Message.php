<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'sender_id', 'body', 'reply_to_id', 'gif_url', 'gif_alt', 'photo_path', 'photo_w', 'photo_h', 'audio_path', 'audio_duration', 'audio_bars', 'read_at', 'deleted_at', 'deleted_by'])]
class Message extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(MessageDeletion::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isGif(): bool
    {
        return ! empty($this->gif_url);
    }

    public function isPhoto(): bool
    {
        return ! empty($this->photo_path);
    }

    public function isAudio(): bool
    {
        return ! empty($this->audio_path);
    }

    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function isDeletedForAll(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isDeletedFor(User $user): bool
    {
        return $this->deletions()->where('user_id', $user->id)->exists();
    }
}
