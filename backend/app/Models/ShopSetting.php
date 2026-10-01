<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Настройки экономики: таблица key/value, поверх config('apex.coins').
 * Пишется из админки, поэтому все числа в config — только дефолт.
 */
class ShopSetting extends Model
{
    protected $table = 'shop_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

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
