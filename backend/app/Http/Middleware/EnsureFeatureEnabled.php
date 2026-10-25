<?php

namespace App\Http\Middleware;

use App\Domains\Control\Services\ControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Закрывает маршрут, если админ выключил функцию на «Пульте»:
 *   ->middleware('feature:shop')
 *
 * Администратор проходит всегда — чтобы мог проверить, что всё работает.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! ControlService::enabled($feature) && ! $request->user()?->isAdmin()) {
            $label = ControlService::FEATURES[$feature] ?? 'Эта функция';

            return response()->json([
                'message' => "{$label}: временно отключено администрацией. Загляните чуть позже.",
                'feature' => $feature,
            ], 423);
        }

        return $next($request);
    }
}
