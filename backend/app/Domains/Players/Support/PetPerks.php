<?php

namespace App\Domains\Players\Support;

/**
 * Перки питомца по уровню. Открываются навсегда и копятся: питомец
 * 25 уровня даёт всё, что открылось на 5, 10, 15 и 20.
 *
 * Экспедиции и арена берут перки того питомца, который пошёл в поход
 * или дерётся. Шахтёр — лучшего из гуляющих по профилю.
 */
class PetPerks
{
    /** Уровень => [ключ, подпись]. */
    public const LIST = [
        5 => ['expedition_coins_10', '+10% монет из экспедиций'],
        10 => ['miner_coins_10', '+10% монет в Шахтёре, пока питомец гуляет по профилю'],
        15 => ['key_chance', '+5% к шансу найти ключ в экспедиции, открыт Незер'],
        20 => ['arena', '+1 бой на арене в день и +25% монет за победу'],
        25 => ['expedition_coins_25', '+25% монет из экспедиций вместо 10%'],
        30 => ['legend', '+20% монет в Шахтёре и 10% шанс двойной добычи в экспедиции'],
        // Дальше перки не дают монет: экономику они не раздувают
        40 => ['expedition_fast', 'Экспедиции на 10% короче'],
        50 => ['attack', '+5% к атаке в бою'],
        60 => ['aura', 'Светящаяся аура вокруг питомца'],
        70 => ['rest', 'Энергия для спаривания восстанавливается на 50% быстрее'],
        80 => ['heritage', '+15% к шансу унаследовать окрас и аксессуар при спаривании'],
        90 => ['bulwark', '+5% к здоровью и защите в бою'],
        100 => ['crown', 'Корона «Легенда ферм» и спаривание на 4 часа короче'],
    ];

    /** Выше этого уровня монетная добыча (экспедиции, арена) больше не растёт. */
    public const COIN_SCALING_CAP = 30;

    public static function expeditionTimeFactor(int $level): float
    {
        return $level >= 40 ? 0.9 : 1.0;
    }

    public static function attackBonus(int $level): float
    {
        return $level >= 50 ? 1.05 : 1.0;
    }

    /** Здоровье и защита. */
    public static function bodyBonus(int $level): float
    {
        return $level >= 90 ? 1.05 : 1.0;
    }

    /** Во сколько раз быстрее восстанавливается энергия. */
    public static function restFactor(int $level): float
    {
        return $level >= 70 ? 1.5 : 1.0;
    }

    /** Добавка к шансу унаследовать черту, в долях. */
    public static function heritageBonus(int $level): float
    {
        return $level >= 80 ? 0.15 : 0.0;
    }

    /** Сколько часов короче спаривание. */
    public static function matingHoursOff(int $level): int
    {
        return $level >= 100 ? 4 : 0;
    }

    /** Перки, открытые к уровню. */
    public static function unlocked(int $level): array
    {
        return array_values(array_map(
            fn ($perk) => $perk[0],
            array_filter(self::LIST, fn ($at) => $level >= $at, ARRAY_FILTER_USE_KEY),
        ));
    }

    /** Для витрины: все перки с отметкой, открыт ли. */
    public static function present(int $level): array
    {
        $result = [];
        foreach (self::LIST as $at => [$key, $label]) {
            $result[] = ['level' => $at, 'key' => $key, 'label' => $label, 'unlocked' => $level >= $at];
        }

        return $result;
    }

    public static function expeditionCoinsBonus(int $level): float
    {
        return match (true) {
            $level >= 25 => 0.25,
            $level >= 5 => 0.10,
            default => 0.0,
        };
    }

    public static function keyChanceBonus(int $level): float
    {
        return $level >= 15 ? 5.0 : 0.0;
    }

    public static function doubleLootChance(int $level): float
    {
        return $level >= 30 ? 10.0 : 0.0;
    }

    public static function minerBonus(int $level): float
    {
        return match (true) {
            $level >= 30 => 0.20,
            $level >= 10 => 0.10,
            default => 0.0,
        };
    }

    public static function arenaBonus(int $level): bool
    {
        return $level >= 20;
    }
}
