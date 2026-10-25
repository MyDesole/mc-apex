<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отмечает время последней активности игрока.
 *
 * Нужно для запасного определения статуса «онлайн»: presence-канал
 * Reverb даёт мгновенный ответ, но если он недоступен, статус считается
 * по этому времени.
 *
 * Обновление идёт одним запросом без событий модели и не на каждый
 * запрос: писать в базу так часто незачем.
 */
class TouchLastSeen
{
    /**
     * Через сколько секунд повторно записывать время.
     *
     * Должно быть заметно меньше окна онлайна (PresenceService): при
     * равных значениях активный игрок периодически выпадал из окна и
     * «мигал» оффлайн между записями.
     */
    private const THROTTLE_SECONDS = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->shouldTouch($user)) {
            $now = now();

            /*
             * Прямое обновление вместо save(): не трогаем updated_at и не
             * запускаем события модели на каждом запросе.
             */
            DB::table('users')
                ->where('id', $user->id)
                ->update(['last_seen_at' => $now]);

            $user->last_seen_at = $now;
        }

        return $next($request);
    }

    /** Пора ли обновлять время. */
    private function shouldTouch($user): bool
    {
        $last = $user->last_seen_at;

        if (! $last) {
            return true;
        }

        return $last->diffInSeconds(now()) >= self::THROTTLE_SECONDS;
    }
}
