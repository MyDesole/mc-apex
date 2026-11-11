<?php

namespace App\Domains\Clan\Support;

use App\Domains\Clan\Models\Clan;

/**
 * Оформление клана: эмблема, цветовая тема, девиз и цвет тега.
 *
 * Часть вариантов открывается с ростом уровня клана — это причина качать клан
 * и повод показать соседям красивую карточку. Сами пресеты живут здесь,
 * в базе хранятся только ключи.
 */
final class ClanStyle
{
    /** Цвет тега доступен с этого уровня клана. */
    public const TAG_COLOR_LEVEL = 2;

    /** ключ => [значок, название, уровень клана] */
    public const EMBLEMS = [
        'star' => ['⭐', 'Звезда', 1],
        'sword' => ['⚔️', 'Мечи', 1],
        'shield' => ['🛡️', 'Щит', 1],
        'skull' => ['💀', 'Череп', 2],
        'flame' => ['🔥', 'Пламя', 2],
        'bolt' => ['⚡', 'Молния', 3],
        'wolf' => ['🐺', 'Волк', 3],
        'crown' => ['👑', 'Корона', 4],
        'dragon' => ['🐉', 'Дракон', 5],
        'diamond' => ['💎', 'Алмаз', 6],
        'phoenix' => ['🦅', 'Феникс', 7],
        'galaxy' => ['🌌', 'Галактика', 8],
    ];

    /** ключ => [название, цвет 1, цвет 2, уровень клана] */
    public const THEMES = [
        'violet' => ['Фиолетовая', '#7c3aed', '#06b6d4', 1],
        'ocean' => ['Океан', '#0ea5e9', '#1e3a8a', 1],
        'ember' => ['Жар', '#f97316', '#b91c1c', 2],
        'forest' => ['Лес', '#22c55e', '#065f46', 2],
        'sunset' => ['Закат', '#f43f5e', '#f59e0b', 3],
        'gold' => ['Золото', '#fbbf24', '#b45309', 4],
        'aurora' => ['Северное сияние', '#34d399', '#8b5cf6', 6],
        'void' => ['Бездна', '#111827', '#7c3aed', 8],
    ];

    public const MOTTO_MAX = 100;

    public const RECRUIT_MAX = 200;

    /** Оформление клана для карточек и страниц; null-поля пропускаются пустыми. */
    public static function payload(Clan $clan): array
    {
        $emblem = self::EMBLEMS[$clan->emblem] ?? null;
        $theme = self::THEMES[$clan->theme] ?? null;

        return [
            'motto' => $clan->motto,
            'emblem' => $emblem ? ['key' => $clan->emblem, 'glyph' => $emblem[0], 'title' => $emblem[1]] : null,
            'theme' => $theme ? ['key' => $clan->theme, 'title' => $theme[0], 'from' => $theme[1], 'to' => $theme[2]] : null,
            'tag_color' => $clan->tag_color,
            'recruit_text' => $clan->recruit_text,
            'min_rating' => (int) $clan->min_rating,
        ];
    }

    /** Все варианты с отметкой «открыто» для текущего уровня клана — для окна настройки. */
    public static function catalog(int $level): array
    {
        $emblems = [];
        foreach (self::EMBLEMS as $key => [$glyph, $title, $need]) {
            $emblems[] = ['key' => $key, 'glyph' => $glyph, 'title' => $title, 'level' => $need, 'unlocked' => $level >= $need];
        }

        $themes = [];
        foreach (self::THEMES as $key => [$title, $from, $to, $need]) {
            $themes[] = ['key' => $key, 'title' => $title, 'from' => $from, 'to' => $to, 'level' => $need, 'unlocked' => $level >= $need];
        }

        return [
            'emblems' => $emblems,
            'themes' => $themes,
            'tag_color_unlocked' => $level >= self::TAG_COLOR_LEVEL,
            'tag_color_level' => self::TAG_COLOR_LEVEL,
            'motto_max' => self::MOTTO_MAX,
            'recruit_max' => self::RECRUIT_MAX,
        ];
    }

    /**
     * Ключи, которые клан ещё не открыл. Пустой массив — всё в порядке.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>  поле => сообщение
     */
    public static function locked(array $data, int $level): array
    {
        $errors = [];

        if (! empty($data['emblem']) && $level < (self::EMBLEMS[$data['emblem']][2] ?? 1)) {
            $errors['emblem'] = 'Эта эмблема откроется на ' . self::EMBLEMS[$data['emblem']][2] . ' уровне клана.';
        }

        if (! empty($data['theme']) && $level < (self::THEMES[$data['theme']][3] ?? 1)) {
            $errors['theme'] = 'Эта тема откроется на ' . self::THEMES[$data['theme']][3] . ' уровне клана.';
        }

        if (! empty($data['tag_color']) && $level < self::TAG_COLOR_LEVEL) {
            $errors['tag_color'] = 'Свой цвет тега откроется на ' . self::TAG_COLOR_LEVEL . ' уровне клана.';
        }

        return $errors;
    }
}
