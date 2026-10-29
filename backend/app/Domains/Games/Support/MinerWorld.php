<?php

namespace App\Domains\Games\Support;

/**
 * Справочник «Шахтёра»: ярусы и их руда, цены блоков, улучшения,
 * артефакты, ежедневные задания и призы недели. Только данные —
 * правила игры в MinerService и MinerProgress.
 */
final class MinerWorld
{
    /** Блок => монеты за него. */
    public const VALUES = [
        // Пещеры
        'dirt' => 1,
        'stone' => 2,
        'coal' => 9,
        'copper' => 14,
        'iron' => 22,
        'redstone' => 27,
        'lapis' => 36,
        'gold' => 54,
        'diamond' => 160,
        'emerald' => 250,
        // Глубинный сланец
        'deepslate' => 4,
        'amethyst' => 65,
        // Незер
        'netherrack' => 2,
        'quartz' => 32,
        'glowstone' => 40,
        'nether_gold' => 50,
        'debris' => 400,
        // Энд
        'end_stone' => 4,
        'chorus' => 70,
        'purpur' => 100,
        'shulker' => 270,
        // Особые: стоят ноль, но что-то делают
        'tnt' => 0,
        'gravel' => 0,
        'lava' => 0,
        'chest' => 0,
        'mimic' => 0,
        'villager' => 0,
        'relic' => 0,
        'bedrock' => 0,
        'obsidian' => 0,
    ];

    /** Пустая порода: сбрасывает комбо. */
    public const ROCK = ['dirt', 'stone', 'deepslate', 'netherrack', 'end_stone'];

    /** Не копаются (кроме незеритовой кирки). */
    public const WALLS = ['bedrock', 'obsidian'];

    /** Редкая руда: на неё действуют Удача и золотая лихорадка. */
    public const RARE = ['lapis', 'gold', 'diamond', 'emerald', 'amethyst', 'nether_gold', 'debris', 'chorus', 'purpur', 'shulker'];

    /** Номер яруса => название, порода и веса блоков по глубине (ряд => веса). */
    public const TIERS = [
        1 => [
            'name' => 'Пещеры',
            'rock' => 'stone',
            'rows' => [
                1 => ['dirt' => 50, 'stone' => 30, 'coal' => 14, 'copper' => 6, 'gravel' => 6],
                3 => ['stone' => 42, 'coal' => 16, 'copper' => 12, 'iron' => 14, 'redstone' => 6, 'tnt' => 4, 'bedrock' => 6, 'gravel' => 5, 'chest' => 2, 'villager' => 1],
                5 => ['stone' => 36, 'iron' => 14, 'redstone' => 10, 'lapis' => 9, 'gold' => 9, 'diamond' => 3, 'tnt' => 6, 'bedrock' => 13, 'lava' => 4, 'chest' => 2, 'villager' => 1, 'relic' => 1],
                6 => ['stone' => 30, 'gold' => 14, 'lapis' => 10, 'diamond' => 9, 'emerald' => 5, 'tnt' => 7, 'bedrock' => 25, 'lava' => 5, 'chest' => 2, 'relic' => 1],
            ],
        ],
        2 => [
            'name' => 'Глубинный сланец',
            'rock' => 'deepslate',
            'rows' => [
                3 => ['deepslate' => 40, 'iron' => 12, 'redstone' => 12, 'lapis' => 10, 'gold' => 10, 'diamond' => 4, 'amethyst' => 4, 'tnt' => 5, 'bedrock' => 10, 'lava' => 6, 'gravel' => 3, 'chest' => 2, 'villager' => 1, 'relic' => 1],
                6 => ['deepslate' => 34, 'redstone' => 10, 'lapis' => 8, 'gold' => 12, 'diamond' => 7, 'emerald' => 3, 'amethyst' => 5, 'tnt' => 6, 'bedrock' => 20, 'lava' => 8, 'chest' => 2, 'villager' => 1, 'relic' => 1],
            ],
        ],
        3 => [
            'name' => 'Незер',
            'rock' => 'netherrack',
            'rows' => [
                3 => ['netherrack' => 44, 'quartz' => 16, 'nether_gold' => 14, 'glowstone' => 6, 'gold' => 3, 'tnt' => 4, 'bedrock' => 8, 'lava' => 10, 'chest' => 2, 'villager' => 1, 'relic' => 1],
                6 => ['netherrack' => 36, 'quartz' => 14, 'nether_gold' => 14, 'glowstone' => 8, 'debris' => 3, 'tnt' => 5, 'bedrock' => 22, 'lava' => 12, 'chest' => 2, 'relic' => 1],
            ],
        ],
        4 => [
            'name' => 'Энд',
            'rock' => 'end_stone',
            'rows' => [
                6 => ['end_stone' => 50, 'chorus' => 14, 'purpur' => 8, 'shulker' => 3, 'obsidian' => 18, 'tnt' => 2, 'chest' => 3, 'relic' => 2],
            ],
        ],
    ];

    /** Улучшения: цены уровней по порядку (сколько цен — столько уровней). */
    public const UPGRADES = [
        'pickaxe' => [
            'name' => 'Кирка',
            'prices' => [800, 2000, 4500, 9000, 16000],
            'about' => '+1 удар за уровень. Незеритовая (5) раскалывает бедрок и обсидиан за 3 удара.',
        ],
        'fortune' => [
            'name' => 'Удача',
            'prices' => [1000, 2500, 5000, 10000, 18000],
            'about' => 'За уровень: редкой руды на 4% больше и +1% шанс двойного дропа.',
        ],
        'lantern' => [
            'name' => 'Фонарь',
            'prices' => [600, 1500, 3500, 7000, 12000],
            'about' => 'Светит вокруг последнего удара: 1 — лава и гравий, 2 — бедрок, 3 — на две клетки, 4 — лава и гравий по всему ярусу, 5 — сундуки, жители и артефакты.',
        ],
        'cart' => [
            'name' => 'Вагонетка',
            'prices' => [1000, 2500, 5000, 9000, 15000],
            'about' => 'Перерыв между забегами короче на 15 секунд за уровень.',
        ],
        'dynamite' => [
            'name' => 'Динамит',
            'prices' => [6000],
            'about' => 'Заряд на каждый забег: взрыв крестом без удара кирки.',
        ],
    ];

    /** Артефакты: с какого яруса попадаются. Каждый разный — +1% к добыче. */
    public const ARTIFACTS = [
        'coin' => ['name' => 'Древняя монета', 'tier' => 1],
        'fossil' => ['name' => 'Окаменелость', 'tier' => 1],
        'compass' => ['name' => 'Ржавый компас', 'tier' => 1],
        'map' => ['name' => 'Карта сокровищ', 'tier' => 2],
        'heart' => ['name' => 'Сердце моря', 'tier' => 2],
        'totem' => ['name' => 'Тотем бессмертия', 'tier' => 3],
        'star' => ['name' => 'Звезда Незера', 'tier' => 3],
        'egg' => ['name' => 'Яйцо дракона', 'tier' => 4],
    ];

    public const ARTIFACT_BONUS = 0.01;
    /** Вся коллекция — ещё сверху. */
    public const SET_BONUS = 0.02;

    /** Ежедневные задания: цель и награда. Каждый день игроку выпадают три. */
    public const QUESTS = [
        'diamonds' => ['text' => 'Добудь 3 алмаза', 'goal' => 3, 'reward' => 300],
        'combo' => ['text' => 'Собери комбо из 5 руд подряд', 'goal' => 5, 'reward' => 250],
        'descend' => ['text' => 'Спустись на ярус ниже', 'goal' => 1, 'reward' => 200],
        'nether' => ['text' => 'Доберись до Незера', 'goal' => 1, 'reward' => 400],
        'chests' => ['text' => 'Открой 2 сундука', 'goal' => 2, 'reward' => 200],
        'blasts' => ['text' => 'Устрой 3 взрыва', 'goal' => 3, 'reward' => 200],
        'coins' => ['text' => 'Добудь 1000 монет за день', 'goal' => 1000, 'reward' => 300],
        'runs' => ['text' => 'Сделай 4 забега', 'goal' => 4, 'reward' => 250],
        'trade' => ['text' => 'Поторгуй с жителем', 'goal' => 1, 'reward' => 200],
        'relic' => ['text' => 'Найди артефакт', 'goal' => 1, 'reward' => 300],
    ];

    /** Сделки жителя: цена — доля добычи забега, но не меньше минимума. */
    public const TRADES = [
        'strikes' => ['text' => '+5 ударов кирки', 'share' => 0.25, 'min' => 30],
        'dynamite' => ['text' => '+1 динамит', 'share' => 0.15, 'min' => 20],
        'map' => ['text' => 'Карта яруса: видно все блоки рядом', 'share' => 0.10, 'min' => 15],
    ];

    /** Место в топе недели => [монеты, сундук для ключа]. */
    public const WEEK_PRIZES = [
        1 => [6000, 'royal'],
        2 => [4000, 'seeker'],
        3 => [2000, 'seeker'],
    ];

    public static function tier(int $tier): array
    {
        return self::TIERS[$tier] ?? self::TIERS[1];
    }

    /** Веса блоков для ряда яруса: первая граница, не меньшая ряда. */
    public static function weights(int $tier, int $row): array
    {
        $rows = self::tier($tier)['rows'];

        foreach ($rows as $upTo => $weights) {
            if ($row <= $upTo) {
                return $weights;
            }
        }

        return end($rows);
    }

    public static function isOre(string $block): bool
    {
        return self::VALUES[$block] > 0 && ! in_array($block, self::ROCK, true);
    }
}
