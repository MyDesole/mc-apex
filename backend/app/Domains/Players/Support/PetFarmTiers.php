<?php

namespace App\Domains\Players\Support;

/**
 * Ступени питомцев для ферм: из каких видов состоит каждая, сколько
 * стоят на Общей ферме и сколько растут. «Ступень» — не редкость
 * предмета в магазине, а положение вида на ферме: от «мусорных» до
 * легендарных. Скрещивание поднимает пару на ступень выше.
 *
 * Ворона (pet-crow) сюда не входит: её выдаёт только администрация.
 */
final class PetFarmTiers
{
    public const ORDER = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

    public const NAMES = [
        'common' => 'Обычный',
        'uncommon' => 'Необычный',
        'rare' => 'Редкий',
        'epic' => 'Эпический',
        'legendary' => 'Легендарный',
    ];

    /** Ступень => виды (effect_value питомца из магазина). */
    public const SPECIES = [
        'common' => ['slime', 'frog', 'bee', 'shroom', 'penguin', 'cloud', 'turtle'],
        'uncommon' => ['cat', 'dog', 'fox', 'owl', 'bunny', 'hawk', 'mole'],
        'rare' => ['panda', 'capybara', 'axolotl', 'octopus', 'jelly', 'bat'],
        'epic' => ['robot', 'ghost', 'golem', 'fairy'],
        'legendary' => ['dragon', 'phoenix', 'unicorn', 'griffin'],
    ];

    /** Цена в ApexCoin на Общей ферме (выросший питомец). Эпиков и легенд там нет. */
    public const COMMON_FARM_PRICE = ['common' => 4000, 'uncommon' => 15000, 'rare' => 45000];

    /** Растёт на Общей ферме, часы [от, до]. */
    public const COMMON_FARM_GROWTH = ['common' => [1, 3], 'uncommon' => [3, 6], 'rare' => [8, 12]];

    /** Что и почём растёт на Личной ферме: цена семени и часы роста. */
    public const PERSONAL_FARM = [
        'rare' => ['price' => 30000, 'hours' => 12],
        'epic' => ['price' => 80000, 'hours' => 24],
        'legendary' => ['price' => 200000, 'hours' => 48],
    ];

    public static function tierOf(?string $species): ?string
    {
        foreach (self::SPECIES as $tier => $list) {
            if (in_array($species, $list, true)) {
                return $tier;
            }
        }

        return null;
    }

    /** Следующая ступень или null, если выше некуда. */
    public static function next(string $tier): ?string
    {
        $index = array_search($tier, self::ORDER, true);

        return $index === false ? null : (self::ORDER[$index + 1] ?? null);
    }

    public static function index(string $tier): int
    {
        return (int) array_search($tier, self::ORDER, true);
    }
}
