<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    /**
     * Получить значение по ключу с дефолтом.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;

        return static::cast($setting->value, $setting->type, $default);
    }

    /**
     * Установить значение.
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): void
    {
        $value = static::encode($value, $type);

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );
    }

    /**
     * Получить все настройки группы.
     */
    public static function group(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => static::cast($s->value, $s->type)])
            ->toArray();
    }

    protected static function cast($value, string $type, $default = null)
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'json' => $value ? json_decode($value, true) : $default,
            default => $value,
        };
    }

    protected static function encode($value, string $type): ?string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
