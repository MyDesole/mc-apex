<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Резолв сущности из URL: число — это id, строка — ник или имя.
 *
 * Логика была продублирована в PlayerController и ClanController
 * почти дословно, включая поиск без учёта регистра.
 */
trait ResolvesFromUrl
{
    /**
     * Найти модель по id или по строковому ключу.
     *
     * @param  class-string<Model>  $model
     * @param  string  $column  колонка для поиска по строке (username, name, slug)
     * @param  array<int, string>  $valueVariants  дополнительные варианты написания
     */
    protected function resolveFromUrl(
        string $model,
        string $value,
        string $column,
        string $notFoundMessage = 'Не найдено.',
        array $valueVariants = [],
    ): Model {
        $value = trim(rawurldecode($value));

        if ($value === '') {
            abort(404, $notFoundMessage);
        }

        if (ctype_digit($value)) {
            return $model::findOrFail((int) $value);
        }

        // Пробуем исходное написание, затем варианты (например, пробелы ↔ подчёркивания)
        $variants = array_unique(array_merge([$value], $valueVariants));

        foreach ($variants as $variant) {
            $found = $model::where($column, $variant)->first()
                ?? $model::whereRaw("LOWER({$column}) = ?", [mb_strtolower($variant)])->first();

            if ($found) {
                return $found;
            }
        }

        abort(404, $notFoundMessage);
    }

    /**
     * Варианты написания, где пробелы и подчёркивания взаимозаменяемы.
     *
     * @return array<int, string>
     */
    protected function underscoreVariants(string $value): array
    {
        return [
            str_replace('_', ' ', $value),
            str_replace(' ', '_', $value),
        ];
    }
}
