<?php

namespace App\Http\Middleware;

use App\Models\ClanMember;
use Closure;
use Illuminate\Http\Request;

class EnsureClanMember
{
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $membership = $user->clanMember;

        if (!$membership) {
            abort(403, 'Вы не в клане.');
        }

        // если нужно конкретное право
        if ($permission && !$membership->can($permission)) {
            abort(403, 'Недостаточно прав.');
        }

        // прокидываем membership в request
        $request->attributes->set('clan_membership', $membership);
        $request->attributes->set('clan', $membership->clan);

        return $next($request);
    }
}
