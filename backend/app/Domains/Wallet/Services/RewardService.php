<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Domains\Shop\Services\ShopSettingService;

/**
 * Расчёт и выдача награды в ApexCoin.
 *
 * Точки входа:
 *  - RewardService::forTierTest()  — вызывается при завершении тир-теста (обе ветки: игрок и тестер)
 *  - RewardService::forAchievement() — вызывается при выдаче ачивки
 */
class RewardService
{
    /**
     * Награда за конкретный тир. 0, если начисления за тесты выключены.
     */
    public static function coinsForTier(string $tier): int
    {
        if (! ShopSettingService::sourceEnabled(CoinTransaction::SOURCE_TIER_TEST)) {
            return 0;
        }

        $table = ShopSettingService::get('tier_test.per_tier', config('apex.coins.tier_test.per_tier'));

        if (! is_array($table)) {
            return 0;
        }

        return (int) ($table[$tier] ?? 0);
    }

    /**
     * Начислить монеты за пройденный тир-тест.
     * Идемпотентно: повторный вызов для того же теста ничего не начислит.
     *
     * @return int начисленная сумма
     */
    public static function forTierTest(TierTest $tierTest): int
    {
        $user = $tierTest->user;

        if (! $user || ! $tierTest->result_tier) {
            return 0;
        }

        $total = 0;

        $perTier = self::coinsForTier($tierTest->result_tier);

        if ($perTier > 0) {
            $total += CoinService::credit(
                $user,
                $perTier,
                CoinTransaction::SOURCE_TIER_TEST,
                "Тир-тест: результат {$tierTest->result_tier} ({$tierTest->mode})",
                'tier_test:' . $tierTest->id,
                [
                    'reference_type' => TierTest::class,
                    'reference_id' => $tierTest->id,
                    'meta' => ['tier' => $tierTest->result_tier, 'mode' => $tierTest->mode],
                ]
            );
        }

        // Разовая доплата за первую в жизни заявку
        $bonus = ShopSettingService::getInt('tier_test.first_test_bonus', 0);

        if ($bonus > 0) {
            $total += CoinService::credit(
                $user,
                $bonus,
                CoinTransaction::SOURCE_TIER_TEST,
                'Первый тир-тест на платформе',
                'tier_test_first:' . $user->id
            );
        }

        return $total;
    }

    /**
     * Размер награды за ачивку: явное значение из админки, иначе base + points * per_point.
     */
    public static function coinsForAchievement(Achievement $achievement): int
    {
        if (! ShopSettingService::sourceEnabled(CoinTransaction::SOURCE_ACHIEVEMENT)) {
            return 0;
        }

        if ($achievement->coin_reward !== null) {
            return (int) $achievement->coin_reward;
        }

        $base = ShopSettingService::getInt('achievement.base', (int) config('apex.coins.achievement.base'));
        $perPoint = ShopSettingService::getInt('achievement.per_point', (int) config('apex.coins.achievement.per_point'));

        return $base + ((int) $achievement->points * $perPoint);
    }

    /**
     * Начислить монеты за ачивку. Идемпотентно: одна ачивка — одна награда.
     */
    public static function forAchievement(User $user, Achievement $achievement): int
    {
        $amount = self::coinsForAchievement($achievement);

        if ($amount <= 0) {
            return 0;
        }

        return CoinService::credit(
            $user,
            $amount,
            CoinTransaction::SOURCE_ACHIEVEMENT,
            "Ачивка «{$achievement->name}»",
            'achievement:' . $user->id . ':' . $achievement->id,
            [
                'reference_type' => Achievement::class,
                'reference_id' => $achievement->id,
                'meta' => ['points' => $achievement->points, 'rarity' => $achievement->rarity ?? null],
            ]
        );
    }

    /**
     * Награда пригласившему за нового игрока.
     * Идемпотентно: за одного приглашённого — одна награда.
     */
    public static function forReferral(User $referrer, User $invited): int
    {
        if (! ShopSettingService::sourceEnabled(CoinTransaction::SOURCE_REFERRAL)) {
            return 0;
        }

        $amount = ShopSettingService::getInt('referral.amount', (int) config('apex.coins.referral.amount'));

        if ($amount <= 0) {
            return 0;
        }

        return CoinService::credit(
            $referrer,
            $amount,
            CoinTransaction::SOURCE_REFERRAL,
            "Приглашён игрок {$invited->username}",
            'referral:' . $invited->id,
            [
                'reference_type' => User::class,
                'reference_id' => $invited->id,
                'actor_id' => $invited->id,
                'meta' => ['invited_username' => $invited->username],
            ]
        );
    }

    /**
     * Приветственный бонус новому игроку, пришедшему по ссылке.
     */
    public static function welcomeBonus(User $invited): int
    {
        if (! ShopSettingService::sourceEnabled(CoinTransaction::SOURCE_REFERRAL)) {
            return 0;
        }

        $amount = ShopSettingService::getInt('referral.welcome_bonus', (int) config('apex.coins.referral.welcome_bonus'));

        if ($amount <= 0) {
            return 0;
        }

        return CoinService::credit(
            $invited,
            $amount,
            CoinTransaction::SOURCE_REFERRAL,
            'Бонус за регистрацию по приглашению',
            'referral_welcome:' . $invited->id
        );
    }

    /**
     * Таблица наград по тирам — для витрины «сколько дают за тест».
     */
    public static function tierTable(): array
    {
        $table = ShopSettingService::get('tier_test.per_tier', config('apex.coins.tier_test.per_tier'));

        return is_array($table) ? $table : [];
    }
}
