<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Боевые действия (подземелья, рейд, арена, Академия, Кузница) живут в
 * Цитадели, вход в которую покупается один раз. Без входа сервер отказывает,
 * а не только прячет здания на карте.
 */
class EnsureCitadelUnlocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->citadel_unlocked_at) {
            return response()->json([
                'message' => 'Вход в Цитадель закрыт: купи его на карте-мире, это делается один раз.',
                'code' => 'citadel_locked',
            ], 403);
        }

        return $next($request);
    }
}
