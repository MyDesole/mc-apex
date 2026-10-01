<?php

namespace App\Services;

use App\Models\ShopSetting;

/**
 * Доступ к настройкам экономики: значение из БД (правится в админке),
 * иначе — дефолт из config/apex.php.
 */
class ShopSettingService
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = ShopSetting::get($key);

        if ($stored !== null) {
            // Значение может быть обёрнуто в ['value' => x] — поддерживаем оба вида
            if (is_array($stored) && array_key_exists('value', $stored)) {
                return $stored['value'];
            }

            return $stored;
        }

        // Дефолт: явный аргумент, иначе соответствующее значение из config/apex.php
        return $default ?? config('apex.coins.' . $key, config('apex.' . $key));
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    /**
     * Значение-массив (таблицы наград, флаги источников).
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        ShopSetting::put($key, $value);
    }

    /**
     * Все сохранённые настройки одним массивом: ['tier_test.per_tier' => [...], ...].
     */
    public static function all(): array
    {
        return ShopSetting::query()
            ->get()
            ->mapWithKeys(fn (ShopSetting $row) => [$row->key => $row->value])
            ->all();
    }

    /**
     * Является ли источник начисления включённым.
     */
    public static function sourceEnabled(string $source): bool
    {
        $sources = self::get('sources');

        if (! is_array($sources)) {
            $sources = config('apex.coins.sources', []);
        }

        return (bool) ($sources[$source] ?? false);
    }
}
