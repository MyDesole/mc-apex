<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Базовый FormRequest проекта.
 *
 * Правила валидации живут в отдельном классе, а не в контроллере: контроллер
 * получает уже проверенные данные и не знает про форматы полей.
 *
 * Авторизация по умолчанию разрешена: доступ к эндпоинтам регулируется
 * middleware (auth/role) и политиками. Если запросу нужна своя проверка прав,
 * наследник переопределяет authorize().
 */
abstract class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Приводим типы до валидации, чтобы «1», «true» и т.п. вели себя предсказуемо.
     */
    protected function prepareForValidation(): void
    {
        //
    }

    /**
     * Часто нужный набор: обязательная строка с границами длины.
     */
    protected function text(string $min = '1', string $max = '255'): array
    {
        return ['required', 'string', "min:{$min}", "max:{$max}"];
    }
}
