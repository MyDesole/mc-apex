<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Куратор стоит выше всех: проходит везде, где пускают администратора
        $allowed = $user && (in_array($user->role, $roles) || ($user->role === 'curator' && in_array('admin', $roles)));

        if (!$allowed) {
            abort(403, 'Недостаточно прав.');
        }

        return $next($request);
    }
}
