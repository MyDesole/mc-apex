<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Ваш аккаунт забанен.',
                'reason' => $user->ban_reason,
            ], 403);
        }

        return $next($request);
    }
}
