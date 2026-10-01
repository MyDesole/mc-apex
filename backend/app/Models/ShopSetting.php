<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Настройки экономики: таблица key/value, поверх config('apex.coins').
 * Пишется из админки, поэтому все числа в config — только дефолт.
 *
 * Значение хранится как JSON. Раньше здесь стоял каст 'value' => 'array',
 * из-за которого скаляры оборачивались в массив: записанная семёрка
 * возвращалась как [7], и читатели (getInt, tierTable) не могли ей
 * воспользоваться — награды из админки не применялись.
 */
class ShopSetting extends Model
{
    protected $table = 'shop_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Разбирает значение из БД: JSON -> PHP, иначе строка как есть.
     */
    public function getValueAttribute(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (! is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        // Не JSON (или битая строка) — отдаём как есть
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    /**
     * Готовит значение к записи: массивы и объекты кодируются в JSON.
     */
    public function setValueAttribute(mixed $value): void
    {
        $this->attributes['value'] = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
