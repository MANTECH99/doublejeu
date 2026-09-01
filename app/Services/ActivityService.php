<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\User;

class ActivityService
{
    /**
     * Toucher l'activité du joueur et mettre à jour le streak du couple.
     */
    public static function touch(User $user): void
    {
        $user->forceFill(['last_active_at' => now()])->save();

        $couple = $user->coupleModel;
        if (! $couple) {
            return;
        }

        $couple->forceFill(['last_activity_at' => now()]);
        self::updateStreak($couple);
        $couple->save();
    }

    public static function updateStreak(Couple $couple): void
    {
        $today = now()->startOfDay();

        if (! $couple->user1 || ! $couple->user2) {
            return;
        }

        $activeToday = ($couple->user1->last_active_at ?? null)?->startOfDay()?->equalTo($today)
            || ($couple->user2->last_active_at ?? null)?->startOfDay()?->equalTo($today);

        if (! $activeToday) {
            return;
        }

        $last = $couple->last_activity_at?->startOfDay();

        if ($last && $last->diffInDays($today) === 1) {
            $couple->streak++;
        } elseif (! $last || $last->diffInDays($today) >= 2) {
            $couple->streak = 1;
        }
    }

    public static function isActiveToday(User $user): bool
    {
        return $user->last_active_at?->startOfDay()?->equalTo(now()->startOfDay()) ?? false;
    }

    public static function daysSinceLastActivity(User $user): int
    {
        if (! $user->last_active_at) {
            return 0;
        }

        return (int) $user->last_active_at->diffInDays(now());
    }
}
