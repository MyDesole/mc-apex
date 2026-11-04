<?php

namespace App\Domains\Players\Support;

use App\Support\GameDay;

/**
 * Задания питомцев. Набор дня и недели одинаков у всех игроков и считается
 * из даты, поэтому хранить расписание не нужно. Награда — ApexCoin и
 * Жетоны удачи; Рубли заданиями не выдаются.
 */
class PetQuests
{
    /** Сколько суточных и недельных заданий на доске. */
    public const DAILY_COUNT = 4;
    public const WEEKLY_COUNT = 3;

    /** Бонус за все суточные задания дня. */
    public const DAILY_BONUS = ['coins' => 2000, 'tokens' => 2];

    /** @var array<string, array{title: string, text: string, event: string, target: int, coins: int, tokens: int, sprite: string}> */
    public const DAILY = [
        'd_mobs' => ['title' => 'Охотник', 'text' => 'Победи мобов в подземельях', 'event' => 'dungeon_mob', 'target' => 12, 'coins' => 900, 'tokens' => 1, 'sprite' => 'mob-rat'],
        'd_bosses' => ['title' => 'Убийца боссов', 'text' => 'Победи боссов этажей', 'event' => 'dungeon_boss', 'target' => 3, 'coins' => 1500, 'tokens' => 1, 'sprite' => 'boss-ratking'],
        'd_clear' => ['title' => 'Зачистка', 'text' => 'Полностью пройди подземелье', 'event' => 'dungeon_clear', 'target' => 1, 'coins' => 2000, 'tokens' => 1, 'sprite' => 'gi-sword-iron'],
        'd_expedition' => ['title' => 'Разведчик', 'text' => 'Забери добычу экспедиций', 'event' => 'expedition', 'target' => 2, 'coins' => 1200, 'tokens' => 1, 'sprite' => 'gi-spear-bronze'],
        'd_arena' => ['title' => 'Гладиатор', 'text' => 'Выиграй бои на арене', 'event' => 'arena_win', 'target' => 3, 'coins' => 1200, 'tokens' => 1, 'sprite' => 'gi-shield-iron'],
        'd_care' => ['title' => 'Заботливый хозяин', 'text' => 'Погладь, покорми или поиграй с питомцами', 'event' => 'care', 'target' => 4, 'coins' => 600, 'tokens' => 1, 'sprite' => 'gi-amulet-bone'],
        'd_craft' => ['title' => 'Кузнец', 'text' => 'Скрафти, перекуй или усиль вещь', 'event' => 'craft', 'target' => 1, 'coins' => 1000, 'tokens' => 1, 'sprite' => 'gi-hammer-iron'],
        'd_market' => ['title' => 'Торговец', 'text' => 'Продай лот на Рынке', 'event' => 'market_sell', 'target' => 1, 'coins' => 800, 'tokens' => 1, 'sprite' => 'gi-medal-bronze'],
    ];

    /** @var array<string, array{title: string, text: string, event: string, target: int, coins: int, tokens: int, sprite: string}> */
    public const WEEKLY = [
        'w_clear' => ['title' => 'Покоритель подземелий', 'text' => 'Зачисти подземелья', 'event' => 'dungeon_clear', 'target' => 6, 'coins' => 12000, 'tokens' => 3, 'sprite' => 'gi-greatsword-steel'],
        'w_arena' => ['title' => 'Чемпион арены', 'text' => 'Выиграй бои на арене', 'event' => 'arena_win', 'target' => 20, 'coins' => 10000, 'tokens' => 3, 'sprite' => 'gi-crown-gold'],
        'w_boss' => ['title' => 'Гроза боссов', 'text' => 'Победи боссов этажей', 'event' => 'dungeon_boss', 'target' => 25, 'coins' => 12000, 'tokens' => 3, 'sprite' => 'gi-skull-bone'],
        'w_expedition' => ['title' => 'Путешественник', 'text' => 'Забери добычу экспедиций', 'event' => 'expedition', 'target' => 10, 'coins' => 9000, 'tokens' => 2, 'sprite' => 'gi-lantern-bronze'],
        'w_craft' => ['title' => 'Мастер-кузнец', 'text' => 'Скрафти, перекуй или усиль вещи', 'event' => 'craft', 'target' => 8, 'coins' => 9000, 'tokens' => 2, 'sprite' => 'gi-hammer-fire'],
        'w_raid' => ['title' => 'Закрывающий Разлом', 'text' => 'Победи в рейде «Разлом»', 'event' => 'raid_win', 'target' => 1, 'coins' => 15000, 'tokens' => 4, 'sprite' => 'gi-eye-void'],
        'w_market' => ['title' => 'Делец', 'text' => 'Продай лоты на Рынке', 'event' => 'market_sell', 'target' => 5, 'coins' => 8000, 'tokens' => 2, 'sprite' => 'gi-medal-steel'],
    ];

    /** Ключ периода: d:дата суток или w:год-неделя по Москве. */
    public static function dayKey(): string
    {
        return 'd:' . GameDay::date();
    }

    public static function weekKey(): string
    {
        return 'w:' . GameDay::local()->format('o-W');
    }

    /**
     * Набор периода: порядок задают хеши ключей с датой, поэтому он
     * одинаков у всех и не повторяется изо дня в день.
     *
     * @param  array<string, array<string, mixed>>  $pool
     * @return array<string, array<string, mixed>>
     */
    public static function pick(array $pool, string $period, int $count): array
    {
        $keys = array_keys($pool);
        usort($keys, fn (string $a, string $b) => crc32($a . $period) <=> crc32($b . $period));

        $chosen = [];

        foreach (array_slice($keys, 0, $count) as $key) {
            $chosen[$key] = $pool[$key];
        }

        return $chosen;
    }

    /** @return array<string, array<string, mixed>> */
    public static function today(): array
    {
        return self::pick(self::DAILY, self::dayKey(), self::DAILY_COUNT);
    }

    /** @return array<string, array<string, mixed>> */
    public static function week(): array
    {
        return self::pick(self::WEEKLY, self::weekKey(), self::WEEKLY_COUNT);
    }
}
