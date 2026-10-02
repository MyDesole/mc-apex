<?php

namespace App\Domains\Shop\Services;

use App\Domains\Shop\Models\ShopSetting;

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

        // Дефолт: явный аргумент, иначе значение из config/apex.php.
        // Проверяем именно передан ли аргумент: явный 0 (или false) — это
        // осмысленный дефолт, но раньше он через ?? перекрывал config,
        // из-за чего first_test_bonus из конфига не начислялся никогда.
        return func_num_args() > 1
            ? $default
            : config('apex.coins.' . $key, config('apex.' . $key));
    }

    /**
     * Целое значение настройки. Без второго аргумента берётся config-дефолт.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = func_num_args() > 1 ? self::get($key, $default) : self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Булево значение настройки. Без второго аргумента берётся config-дефолт.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = func_num_args() > 1 ? self::get($key, $default) : self::get($key);

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
