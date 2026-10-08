<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к эндпоинтам для майнкрафт-сервера.
 *
 * Плагин ходит с общим секретом в заголовке. Сравнение постоянного
 * времени, иначе секрет можно было бы подобрать по времени ответа.
 */
class EnsureMinecraftServer
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.minecraft.server_key');
        $given = (string) $request->header('X-Minecraft-Server-Key', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(403, 'Недоступно.');
        }

        return $next($request);
    }
}
