<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Services\Concerns\AbortsWithMessage;

class AchievementService
{
    /** Размер порции игроков при массовом пересчёте. */
    public const CHUNK_SIZE = 200;

    /**
     * Ключ идемпотентности награды за ачивку.
     *
     * Тот же ключ использует RewardService: по нему определяем,
     * оплачена ли ачивка, и не платим второй раз.
     */
    public static function rewardKey(User $user, Achievement $achievement): string
    {
        return 'achievement:' . $user->id . ':' . $achievement->id;
    }

    /**
     * Пересчитать ачивки и доплатить за неоплаченные.
     *
     * Нужно, когда ачивка была выставлена без награды (источник был
     * выключен или coin_reward не задан) либо когда состав ачивок
     * менялся и часть из них у игроков не проставлена.
     *
     * @param  int|null  $userId  только один игрок, иначе все
     * @param  bool  $dryRun  посчитать, но ничего не менять
     * @return array{
     *     users:int, granted:int, unpaid:int, paid:int, paid_amount:int, failed:int
     * }
     */
    public static function syncAchievements(?int $userId = null, bool $dryRun = false): array
    {
        $report = [
            'users' => 0,
            'granted' => 0,
            'unpaid' => 0,
            'paid' => 0,
            'paid_amount' => 0,
            'failed' => 0,
        ];

        $query = User::query()->orderBy('id');

        if ($userId) {
            $query->whereKey($userId);
        }

        // Обрабатываем порциями: игроков может быть много,
        // а на каждого нужны связи клана и ачивок
        $query->chunkById(self::CHUNK_SIZE, function ($users) use (&$report, $dryRun) {
            foreach ($users as $user) {
                $report['users']++;

                try {
                    $report = self::syncOne($user, $report, $dryRun);
                } catch (Throwable $e) {
                    $report['failed']++;
                }
            }
        });

        return $report;
    }

    /**
     * Пересчёт одного игрока.
     *
     * @param  array<string, int>  $report
     * @return array<string, int>
     */
    private static function syncOne(User $user, array $report, bool $dryRun): array
    {
        $before = $user->achievements()->pluck('achievements.id')->all();

        // 1. Довыставляем ачивки по текущим условиям
        if (! $dryRun) {
            self::check($user);
        }

        $after = $user->achievements()->pluck('achievements.id')->all();

        // В режиме проверки считаем, что было бы выдано,
        // не трогая базу: сверяем условия вслепую
        $newlyGranted = $dryRun
            ? self::wouldGrant($user, $before)
            : count(array_diff($after, $before));

        $report['granted'] += $newlyGranted;

        // 2. Доплачиваем за ачивки без награды
        $achievements = $user->achievements()->get();

        // Ключи уже начисленных награда — одним запросом на игрока
        $paidKeys = CoinTransaction::where('user_id', $user->id)
            ->whereNotNull('idempotency_key')
            ->where('source', CoinTransaction::SOURCE_ACHIEVEMENT)
            ->pluck('idempotency_key')
            ->all();

        $paidKeys = array_flip($paidKeys);

        foreach ($achievements as $achievement) {
            $key = self::rewardKey($user, $achievement);

            if (isset($paidKeys[$key])) {
                continue;
            }

            $amount = RewardService::coinsForAchievement($achievement);

            if ($amount <= 0) {
                // Награда не положена: источник выключен или нулевая ставка
                continue;
            }

            $report['unpaid']++;

            if ($dryRun) {
                $report['paid']++;
                $report['paid_amount'] += $amount;
                continue;
            }

            $credited = RewardService::forAchievement($user, $achievement);

            if ($credited > 0) {
                $report['paid']++;
                $report['paid_amount'] += $credited;
            }
        }

        return $report;
    }

    /**
     * Сколько ачивок было бы выдано игроку прямо сейчас.
     *
     * Нужно для режима проверки: условия считаем, но ничего не пишем.
     *
     * @param  array<int, int>  $already
     */
    private static function wouldGrant(User $user, array $already): int
    {
        $codes = self::eligibleCodes($user);

        $owned = Achievement::whereIn('id', $already)->pluck('code')->all();

        return count(array_diff($codes, $owned));
    }

    /**
     * Коды ачивок, условия которых выполнены у игрока.
     *
     * @return array<int, string>
     */
    private static function eligibleCodes(User $user): array
    {
        $codes = [];

        $clanMember = $user->clanMember;

        if ($clanMember) {
            $codes[] = 'clan_joined';

            if ($clanMember->role === 'leader') {
                $codes[] = 'clan_leader';
            } elseif ($clanMember->role === 'officer') {
                $codes[] = 'clan_officer';
            }

            $wins = \App\Models\ClanWar::where('winner_clan_id', $clanMember->clan_id)->count();

            if ($wins >= 1) $codes[] = 'clan_war_win';
            if ($wins >= 5) $codes[] = 'clan_war_5';
        }

        $tierMap = ['E' => 'tier_e', 'D' => 'tier_d', 'C' => 'tier_c', 'B' => 'tier_b', 'A' => 'tier_a'];
        $order = ['E', 'D', 'C', 'B', 'A'];

        if ($user->tier && isset($tierMap[$user->tier])) {
            $idx = array_search($user->tier, $order, true);

            if ($idx !== false) {
                for ($i = 0; $i <= $idx; $i++) {
                    $codes[] = $tierMap[$order[$i]];
                }
            }
        }

        if ($user->tier === 'S') {
            $codes[] = 'tier_s';
        }

        if ($user->tier === 'S+') {
            $codes[] = 'tier_s_plus';
            $codes[] = 'tier_s';

            foreach ($order as $t) {
                $codes[] = $tierMap[$t];
            }
        }

        $testCount = $user->tierTests()->count();

        if ($testCount >= 1) $codes[] = 'tier_test_first';
        if ($testCount >= 5) $codes[] = 'tier_test_5';

        $friendsCount = \App\Models\Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->count();

        if ($friendsCount >= 5) $codes[] = 'friends_5';
        if ($friendsCount >= 20) $codes[] = 'friends_20';

        if ($user->avatar && $user->bio && $user->cover_path
            && $user->socials && collect($user->socials)->filter()->count() >= 2) {
            $codes[] = 'profile_complete';
        }

        if ($user->tier_score > 0) {
            $position = User::where('tier_score', '>', $user->tier_score)->count() + 1;

            if ($position <= 10) $codes[] = 'top_10';
            if ($position === 1) $codes[] = 'top_1';
        }

        return array_values(array_unique($codes));
    }

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

        // Награда в ApexCoin за ачивку (идемпотентно на стороне CoinService)
        \App\Services\RewardService::forAchievement($user, $achievement);

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
