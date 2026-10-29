<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * throttle:N,M со своим счётчиком у каждого маршрута.
 *
 * Штатный ThrottleRequests без префикса ведёт один счётчик на игрока
 * для всех маршрутов: 30 копаний в шахте съедали лимит «Забрать» (20),
 * ходы на арене — лимит нового боя, и игрок ловил «Too Many Attempts».
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return sha1(parent::resolveRequestSignature($request) . '|' . $request->method() . '|' . ($route?->uri() ?? $request->path()));
    }
}
