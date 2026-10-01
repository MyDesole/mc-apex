<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Настройки наград магазина.
 *
 * Поддерживаются оба вида ключей: плоские с точкой («daily_bonus.amount»),
 * которые отправляет админка, и вложенные массивы. Иначе правила для
 * плоских ключей не срабатывают: validated() трактует точку как путь.
 */
class UpdateShopRewardsRequest extends BaseFormRequest
{
    private const SCALAR_KEYS = [
        // Таблица наград по тирам: значение-массив под плоским ключом
        'tier_test.per_tier',
        'tier_test.first_test_bonus',
        'achievement.base',
        'achievement.per_point',
        'daily_bonus.amount',
        'daily_bonus.enabled',
        'gift.fee_percent',
        'gift.daily_limit',
        'gift.min',
        'gift.max',
        'gift.enabled',
    ];

    public function rules(): array
    {
        return [
            // Плоские ключи с точкой
            'tier_test.first_test_bonus' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'achievement.base' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'achievement.per_point' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'daily_bonus.amount' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'daily_bonus.enabled' => ['sometimes', 'boolean'],
            'gift.fee_percent' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'gift.daily_limit' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'gift.min' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'gift.max' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'gift.enabled' => ['sometimes', 'boolean'],

            // Вложенный вид
            'tier_test' => ['sometimes', 'array'],
            'tier_test.per_tier' => ['sometimes', 'array'],
            'tier_test.per_tier.*' => ['integer', 'min:0', 'max:1000000'],
            'achievement' => ['sometimes', 'array'],
            'daily_bonus' => ['sometimes', 'array'],
            'gift' => ['sometimes', 'array'],

            'sources' => ['sometimes', 'array'],
            'sources.*' => ['boolean'],
        ];
    }

    /**
     * Разворачивает вложенный вид настроек в плоские ключи с точкой.
     *
     * Из ['tier_test' => ['first_test_bonus' => 7]] получается
     * ['tier_test.first_test_bonus' => 7]. Значения-массивы (таблица
     * per_tier, словарь sources) остаются массивами, но ключ плоский.
     *
     * @return array<string, mixed>
     */
    public function flattenGroups(): array
    {
        // 'sources' — самодостаточный словарь, его не разворачиваем
        $keepWhole = ['sources'];

        $flat = [];

        foreach ($this->validated() as $key => $value) {
            if (! is_array($value) || in_array($key, $keepWhole, true)) {
                $flat[$key] = $value;
                continue;
            }

            foreach ($value as $innerKey => $innerValue) {
                $flat["{$key}.{$innerKey}"] = $innerValue;
            }
        }

        return $flat;
    }

    /**
     * Все настройки в плоском виде: вложенные плюс присланные с точкой.
     * Ими админка правит награды напрямую.
     *
     * @return array<string, mixed>
     */
    public function allFlat(): array
    {
        return array_merge($this->flattenGroups(), $this->flatScalars());
    }

    private function flatScalars(): array
    {
        // Берём СЫРЫЕ входные данные, а не $this->has(): has() трактует точку
        // как путь и ищет вложенный ключ, поэтому плоский 'daily_bonus.amount'
        // не находился вовсе.
        $input = $this->all();

        $result = [];

        foreach (self::SCALAR_KEYS as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $result[$key] = $input[$key];
            }
        }

        return $result;
    }
}
