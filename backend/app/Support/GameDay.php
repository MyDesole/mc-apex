<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Игровые сутки по Москве.
 *
 * Время в базе и в приложении остаётся в UTC, но суточные лимиты, бонусы
 * и сбросы начинаются в полночь по Москве, а не в 03:00, как при UTC.
 */
final class GameDay
{
    public const TIMEZONE = 'Europe/Moscow';

    /** Дата текущих игровых суток: значение для столбцов `day`. */
    public static function date(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /** Начало суток в UTC: им сравнивают `created_at`, `updated_at` и подобные. */
    public static function start(): Carbon
    {
        return self::local()->setTimezone('UTC');
    }

    /** Начало суток по Москве: для арифметики по календарным дням. */
    public static function local(): Carbon
    {
        return Carbon::now(self::TIMEZONE)->startOfDay();
    }
}
