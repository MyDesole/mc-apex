<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Превью ссылок для ботов Discord/Telegram — nginx шлёт их сюда (backend/deploy/nginx-og.conf)
Route::prefix('og')->middleware('throttle:120,1')->group(function () {
    $og = \App\Domains\Og\Controllers\OgController::class;

    Route::get('/user/{user}/image.png', [$og, 'userImage']);
    Route::get('/user/{user}', [$og, 'user']);
    Route::get('/clan/{clan}/image.png', [$og, 'clanImage']);
    Route::get('/clan/{clan}', [$og, 'clan']);
    Route::get('/changelog/{slug}/image.png', [$og, 'changelogImage']);
    Route::get('/changelog/{slug}', [$og, 'changelog']);
});
