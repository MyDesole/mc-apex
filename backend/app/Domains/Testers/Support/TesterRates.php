<?php

namespace App\Domains\Testers\Support;

use App\Domains\Core\Models\SiteSetting;

/**
 * Ставки тестера: сколько рублей и ApexCoin за один тест.
 *
 * Базовые значения задаёт админ, а множитель по тиру позволяет платить
 * за S+ больше, чем за E. Настройки лежат в site_settings группой tester,
 * поэтому их видно и правится в общей админке настроек.
 */
final class TesterRates
{
    public const GROUP = 'tester';

    public const DEFAULT_RUB = 50;
    public const DEFAULT_COINS = 500;

    /** Порядок тиров от сильного к слабому — как в заявках. */
    public const TIERS = ['S+', 'S', 'A', 'B', 'C', 'D', 'E'];

    public const DEFAULT_MULTIPLIERS = [
        'S+' => 2.0,
        'S' => 1.6,
        'A' => 1.3,
        'B' => 1.1,
        'C' => 1.0,
        'D' => 0.9,
        'E' => 0.8,
    ];

    /** @return array{pay_rub:int, pay_coins:int, tier_multipliers:array<string,float>} */
    public static function settings(): array
    {
        $stored = SiteSetting::group(self::GROUP);

        return [
            'pay_rub' => (int) ($stored['pay_rub'] ?? self::DEFAULT_RUB),
            'pay_coins' => (int) ($stored['pay_coins'] ?? self::DEFAULT_COINS),
            'tier_multipliers' => array_merge(
                self::DEFAULT_MULTIPLIERS,
                (array) ($stored['tier_multipliers'] ?? []),
            ),
        ];
    }

    public static function multiplier(?string $tier): float
    {
        if (! $tier) {
            return 1.0;
        }

        return (float) (self::settings()['tier_multipliers'][$tier] ?? 1.0);
    }

    /**
     * Сколько начислить за тест с таким тиром.
     *
     * @return array{rub:int, coins:int}
     */
    public static function reward(?string $tier): array
    {
        $settings = self::settings();
        $multiplier = self::multiplier($tier);

        return [
            'rub' => (int) round($settings['pay_rub'] * $multiplier),
            'coins' => (int) round($settings['pay_coins'] * $multiplier),
        ];
    }

    public static function save(int $rub, int $coins, array $multipliers): void
    {
        SiteSetting::set('pay_rub', max(0, $rub), 'int', self::GROUP);
        SiteSetting::set('pay_coins', max(0, $coins), 'int', self::GROUP);

        // В множители пускаем только известные тиры и разумные значения
        $clean = [];

        foreach (self::TIERS as $tier) {
            $value = $multipliers[$tier] ?? self::DEFAULT_MULTIPLIERS[$tier];
            $clean[$tier] = round(min(10, max(0, (float) $value)), 2);
        }

        SiteSetting::set('tier_multipliers', $clean, 'json', self::GROUP);
    }
}
