<?php

namespace App\Domains\Clan\Support;

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

    /**
     * Эффекты подсветки.
     *
     * Ключи совпадают с классами .effect-* в ClansView и с
     * frontend/src/data/clanHighlight.js, поэтому купленный эффект
     * сразу отрисовывается на карточке клана.
     */
    public const EFFECTS = [
        'glow',       // мягкое свечение
        'pulse',      // пульсация
        'gradient',   // переливающийся градиент
        'fire',       // огонь
        'ice',        // лёд
        'aurora',     // северное сияние
        'legendary',  // золотое сияние
    ];

    /** Цвет по умолчанию, если игрок не выбирал. */
    public const DEFAULT_COLOR = 'gold';

    /**
     * Эффект по умолчанию: статичная подсветка цветом без свечения.
     *
     * Свечение — отдельный продаваемый эффект, поэтому по умолчанию
     * клан просто подсвечен своим цветом.
     */
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
