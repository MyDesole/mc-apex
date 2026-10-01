<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Единые ответы API.
 *
 * Контроллеры возвращают результат сервиса, а форму ответа задаёт этот трейт.
 * Так формат успешного ответа и ошибок не расползается по методам.
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, array $extra = []): JsonResponse
    {
        $payload = [];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json(array_merge($payload, $extra));
    }

    protected function created(mixed $data = null, array $extra = []): JsonResponse
    {
        return $this->ok($data, $extra)->setStatusCode(201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    /**
     * Ответ с ошибкой. Код по умолчанию — 422 (невалидные данные).
     */
    protected function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $extra), $status);
    }
}
