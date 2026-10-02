<?php

namespace App\Support;

/**
 * Наборы цвета и эффекта для подсветки клана.
 *
 * Ключи совпадают с effect_value предметов магазина и с
 * frontend/src/data/clanHighlight.js — там же лежит оформление.
 */
class ClanHighlight
{
    /** Цвета подсветки. */
    public const COLORS = [
        'gold',
        'crimson',
        'cyan',
        'violet',
        'emerald',
        'rose',
    ];

    /** Эффекты подсветки. */
    public const EFFECTS = [
        'frame',      // статичная рамка (базовый)
        'glow',       // мягкое свечение
        'pulse',      // пульсация
        'animated',   // переливающийся градиент
        'legendary',  // золотое сияние с блеском
    ];

    /** Цвет по умолчанию, если игрок не выбирал. */
    public const DEFAULT_COLOR = 'gold';

    /** Эффект по умолчанию. */
    public const DEFAULT_EFFECT = 'frame';

    public static function isValidColor(?string $color): bool
    {
        return $color !== null && in_array($color, self::COLORS, true);
    }

    public static function isValidEffect(?string $effect): bool
    {
        return $effect !== null && in_array($effect, self::EFFECTS, true);
    }
}
