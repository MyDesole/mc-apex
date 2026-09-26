<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;

class AchievementService
{
    /**
     * Выдать ачивку пользователю. Идемпотентно.
     */
    public static function grant(User $user, string $code): bool
    {
        $achievement = Achievement::byCode($code);
        if (!$achievement) return false;

        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return false;
        }

        $user->achievements()->attach($achievement->id, [
            'earned_at' => now(),
        ]);

        // 👇 уведомление
        $user->notify(new \App\Notifications\AchievementGrantedNotification($achievement));

        return true;
    }

    /**
     * Массовая проверка всех возможных ачивок для юзера.
     * Дёргается после значимых действий.
     */
    public static function check(User $user): void
    {
        // Клан
        $clanMember = $user->clanMember;
        if ($clanMember) {
            self::grant($user, 'clan_joined');

            if ($clanMember->role === 'leader') {
                self::grant($user, 'clan_leader');
            } elseif ($clanMember->role === 'officer') {
                self::grant($user, 'clan_officer');
            }

            $wins = \App\Models\ClanWar::where('winner_clan_id', $clanMember->clan_id)->count();
            if ($wins >= 1) self::grant($user, 'clan_war_win');
            if ($wins >= 5) self::grant($user, 'clan_war_5');
        }

        // Тиры E–A по проценту. S и S+ — за турниры, здесь НЕ выдаём.
        $tierMap = [
            'E' => 'tier_e',
            'D' => 'tier_d',
            'C' => 'tier_c',
            'B' => 'tier_b',
            'A' => 'tier_a',
        ];

        $order = ['E', 'D', 'C', 'B', 'A'];

        if ($user->tier && isset($tierMap[$user->tier])) {
            $idx = array_search($user->tier, $order);
            if ($idx !== false) {
                for ($i = 0; $i <= $idx; $i++) {
                    self::grant($user, $tierMap[$order[$i]]);
                }
            }
        }

        // Отдельно: если у юзера уже S — выдаём tier_s
        if ($user->tier === 'S') {
            self::grant($user, 'tier_s');
        }

        // Отдельно: если у юзера S+ — выдаём tier_s_plus
        if ($user->tier === 'S+') {
            self::grant($user, 'tier_s_plus');
            // И заодно tier_s, потому что S+ «включает» S
            self::grant($user, 'tier_s');
            // И все предыдущие тиры
            foreach (['E', 'D', 'C', 'B', 'A'] as $t) {
                self::grant($user, $tierMap[$t]);
            }
        }

        // Тир-тесты
        $testCount = $user->tierTests()->count();
        if ($testCount >= 1) self::grant($user, 'tier_test_first');
        if ($testCount >= 5) self::grant($user, 'tier_test_5');

        // Друзья
        $friendsCount = \App\Models\Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->count();

        if ($friendsCount >= 5) self::grant($user, 'friends_5');
        if ($friendsCount >= 20) self::grant($user, 'friends_20');

        // Полный профиль
        if ($user->avatar && $user->bio && $user->cover_path
            && $user->socials && collect($user->socials)->filter()->count() >= 2) {
            self::grant($user, 'profile_complete');
        }

        // Топ
        if ($user->tier_score > 0) {
            $position = User::where('tier_score', '>', $user->tier_score)->count() + 1;
            if ($position <= 10) self::grant($user, 'top_10');
            if ($position === 1) self::grant($user, 'top_1');
        }
    }
}
