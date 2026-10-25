<?php

namespace App\Domains\Achievements\Services;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use App\Domains\Wallet\Services\RewardService;
use Throwable;

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
        return app(AchievementRules::class)->eligible($user);
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
        \App\Domains\Wallet\Services\RewardService::forAchievement($user, $achievement);

        // 👇 уведомление
        $user->notify(new \App\Domains\Achievements\Notifications\AchievementGrantedNotification($achievement));

        self::announce($user, $achievement);

        return true;
    }

    /**
     * Событие в ленте «Сейчас на APEX».
     *
     * attach() не даёт событий модели, поэтому каждый путь выдачи
     * (автоматический и ручной из админки) вызывает это явно.
     */
    public static function announce(User $user, Achievement $achievement): void
    {
        app(\App\Domains\Activity\Services\ActivityFeed::class)->record(
            \App\Domains\Activity\Models\Activity::ACHIEVEMENT_EARNED,
            $user,
            [
                'achievement' => [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'icon' => $achievement->icon,
                    'color' => $achievement->color,
                    'rarity' => $achievement->rarity,
                ],
            ],
        );
    }

    /**
     * Проверка ачивок игрока. Дёргается после значимых действий.
     *
     * Тиры S и S+ даются за турниры, но если тир уже стоит, связанные
     * ачивки выдаются здесь же.
     *
     * @param  array<int, string>|null  $groups  какие группы проверять
     *         (AchievementRules::GROUPS); null — все
     */
    public static function check(User $user, ?array $groups = null): void
    {
        $codes = app(AchievementRules::class)->eligible($user, $groups);

        if (! $codes) {
            return;
        }

        // Уже полученные пропускаем одним запросом, а не проверкой на каждый код
        $owned = $user->achievements()->pluck('code')->all();

        foreach (array_diff($codes, $owned) as $code) {
            self::grant($user, $code);
        }
    }
}
