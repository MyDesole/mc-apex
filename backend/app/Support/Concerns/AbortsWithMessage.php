<?php

namespace App\Support\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Ошибки бизнес-правил в формате, ожидаемом API.
 *
 * В проекте исторически два разных формата, и оба надо сохранить:
 *
 *  1. Плоский {"message": "..."} — так отвечали abort_if(...) в кланах.
 *     Для него — abortWith()/abortUnprocessable().
 *
 *  2. Ошибки по полям {"message": "...", "errors": {"field": ["..."]}} —
 *     так отвечает ValidationException, и этого ждёт фронтенд авторизации.
 *     Для него — invalid().
 */
trait AbortsWithMessage
{
    /**
     * Плоский ответ с сообщением и нужным кодом (как abort_if).
     */
    protected function abortWith(string $status, string $message): never
    {
        abort(response()->json(['message' => $message], (int) $status));
    }

    protected function abortUnprocessable(string $message): never
    {
        $this->abortWith('422', $message);
    }

    protected function abortForbidden(string $message = 'Недостаточно прав.'): never
    {
        $this->abortWith('403', $message);
    }

    /**
     * Ошибка валидации, привязанная к конкретному полю.
     * Сохраняет структуру errors, которую читает фронтенд.
     */
    protected function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
