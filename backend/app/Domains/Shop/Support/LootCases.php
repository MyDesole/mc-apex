<?php

namespace App\Domains\Shop\Support;

/**
 * Кейсы с косметикой. Шансы в процентах по исходу: пусто, монеты или
 * предмет нужной редкости. Бесплатный кейс открывается раз в сутки,
 * платные — за ApexCoin с повышенными шансами и гарантией эпика.
 */
final class LootCases
{
    public const RARITIES = ['common', 'rare', 'epic', 'legendary'];

    // Исходы без предмета
    public const NOTHING = 'nothing';
    public const COINS = 'coins';

    // Платный кейс: если столько открытий подряд не было эпика и выше,
    // следующее гарантированно эпическое или легендарное
    public const PITY_AFTER = 10;

    // Повтор предмета возвращает долю его цены монетами
    public const DUPLICATE_SHARE = 0.15;

    private const CASES = [
        'daily' => [
            'name' => 'Ежедневный сундук',
            'description' => 'Бесплатно раз в день. Загляни завтра — будет новый.',
            'price' => 0,
            'free_daily' => true,
            'theme' => 'oak',
            'coins' => [10, 60],
            'chances' => ['nothing' => 25, 'coins' => 30, 'common' => 30, 'rare' => 11, 'epic' => 3.4, 'legendary' => 0.6],
        ],
        'seeker' => [
            'name' => 'Сундук искателя',
            'description' => 'Шанс на редкое и эпическое в разы выше. Гарантия эпика каждые 10 открытий.',
            'price' => 750,
            'free_daily' => false,
            'theme' => 'amethyst',
            'coins' => [150, 600],
            'chances' => ['nothing' => 5, 'coins' => 15, 'common' => 25, 'rare' => 33, 'epic' => 18, 'legendary' => 4],
        ],
        'royal' => [
            'name' => 'Королевский сундук',
            'description' => 'Никогда не бывает пустым и без обычных предметов. Легендарка — в среднем каждый седьмой раз.',
            'price' => 2500,
            'free_daily' => false,
            'theme' => 'gold',
            'coins' => [800, 2000],
            'chances' => ['nothing' => 0, 'coins' => 10, 'common' => 0, 'rare' => 40, 'epic' => 35, 'legendary' => 15],
        ],
    ];

    public static function all(): array
    {
        $cases = [];
        foreach (self::CASES as $slug => $case) {
            $cases[$slug] = ['slug' => $slug, ...$case];
        }

        return $cases;
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function isItemOutcome(string $outcome): bool
    {
        return in_array($outcome, self::RARITIES, true);
    }
}
