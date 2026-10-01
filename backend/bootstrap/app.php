<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'not.banned' => \App\Http\Middleware\EnsureUserIsNotBanned::class,
            'clan.member' => \App\Http\Middleware\EnsureClanMember::class,
        ]);
        // API-приложение без веб-роутов входа: гость должен получать 401 JSON,
        // а не попытку редиректа на несуществующий маршрут login (это давало 500).
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);
        // Проверка бана во всей группе api: для гостей middleware ничего
        // не делает, а на защищённых роутах выполняется после аутентификации.
        // Раньше здесь создавалась группа-тень с именем алиаса, из-за неё
        // аутентификация не работала ни на одном роуте, а гость получал 500
        // вместо 401.
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EnsureUserIsNotBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();
