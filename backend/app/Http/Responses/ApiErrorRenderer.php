<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Ошибки API человеческим языком.
 *
 * Штатный обработчик Laravel отдаёт «Unauthenticated.», «Server Error»,
 * «No query results for model [App\…\Clan] 7» — эти строки попадали прямо
 * в интерфейс. Здесь каждое исключение получает понятный русский текст
 * с подсказкой, что делать. Сообщения, которые код задал сам
 * (abort(403, 'Вы не в клане.')), остаются как есть.
 */
class ApiErrorRenderer
{
    /** Тексты по коду ответа: когда своего сообщения у ошибки нет. */
    private const BY_STATUS = [
        400 => 'Запрос составлен неверно. Обновите страницу и попробуйте ещё раз.',
        401 => 'Нужно войти в аккаунт.',
        403 => 'Недостаточно прав для этого действия.',
        404 => 'Не найдено. Возможно, запись удалена или ссылка устарела.',
        405 => 'Такое действие здесь не поддерживается.',
        408 => 'Сервер не дождался запроса. Попробуйте ещё раз.',
        409 => 'Данные успели измениться. Обновите страницу и попробуйте ещё раз.',
        413 => 'Файл слишком большой. Выберите файл поменьше.',
        419 => 'Сессия устарела. Обновите страницу и попробуйте ещё раз.',
        422 => 'Проверьте введённые данные.',
        429 => 'Слишком много запросов. Подождите немного и попробуйте снова.',
        500 => 'Что-то сломалось на нашей стороне. Попробуйте ещё раз через минуту.',
        502 => 'Сайт обновляется. Попробуйте через минуту.',
        503 => 'Сайт обновляется. Попробуйте через минуту.',
        504 => 'Сервер долго не отвечает. Попробуйте ещё раз через минуту.',
    ];

    /**
     * Ответ на исключение или null, если его должен отрисовать Laravel
     * (ошибки валидации — у них свой формат с полем errors).
     */
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return null;
        }

        $status = self::status($e);

        // В режиме отладки 500-е показываем как есть: со стеком, для разработчика
        if ($status >= 500 && ! $e instanceof HttpExceptionInterface && config('app.debug')) {
            return null;
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return response()->json(['message' => self::message($e, $status, $headers)], $status, $headers);
    }

    private static function status(Throwable $e): int
    {
        return match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => $e->status() ?? 403,
            $e instanceof ModelNotFoundException, $e instanceof RecordsNotFoundException => 404,
            $e instanceof TokenMismatchException => 419,
            $e instanceof PostTooLargeException => 413,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    private static function message(Throwable $e, int $status, array $headers): string
    {
        if ($e instanceof ThrottleRequestsException) {
            $seconds = (int) ($headers['Retry-After'] ?? 0);

            return $seconds > 0
                ? 'Слишком много запросов. Подождите '.self::wait($seconds).' и попробуйте снова.'
                : self::BY_STATUS[429];
        }

        // Своё сообщение ошибки оставляем, только если оно написано для людей.
        // Сообщение о ненайденной модели содержит имя класса — его не показываем.
        $own = $e instanceof ModelNotFoundException ? '' : $e->getMessage();

        if ($status < 500 && $own !== '' && preg_match('/\p{Cyrillic}/u', $own)) {
            return $own;
        }

        return self::BY_STATUS[$status]
            ?? ($status >= 500 ? self::BY_STATUS[500] : 'Не получилось выполнить действие. Попробуйте ещё раз.');
    }

    private static function wait(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' сек.';
        }

        return (int) ceil($seconds / 60).' мин.';
    }
}
