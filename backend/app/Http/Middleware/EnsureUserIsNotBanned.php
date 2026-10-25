<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            $token = $user->currentAccessToken();

            // Вход по токену API — удаляем токен. Вход через сайт (сессия) даёт
            // TransientToken без delete(): там завершаем сессию, иначе был 500
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            } elseif ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Ваш аккаунт забанен.',
                'reason' => $user->ban_reason,
            ], 403);
        }

        return $next($request);
    }
}
