<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\ClanEventController;
use App\Http\Controllers\ClanWarController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TierTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Игроки
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{user}', [PlayerController::class, 'show']);
    Route::put('/players/me', [PlayerController::class, 'updateMe']);
    Route::post('/players/me/avatar/remove', [PlayerController::class, 'removeAvatar']);
    Route::post('/players/me/cover/remove', [PlayerController::class, 'removeCover']);
    Route::match(['put', 'post'], '/players/me', [PlayerController::class, 'updateMe']);
    Route::put('/players/me/aspects', [PlayerController::class, 'updateAspects']);

    // Тир-тесты
    Route::get('/tier-tests', [TierTestController::class, 'index']);
    Route::post('/tier-tests', [TierTestController::class, 'store']);
    Route::get('/tier-tests/{tierTest}', [TierTestController::class, 'show']);
    Route::put('/tier-tests/{tierTest}', [TierTestController::class, 'update']);

    // Друзья
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/{user}', [FriendController::class, 'store']);
    Route::post('/friends/{user}/accept', [FriendController::class, 'accept']);
    Route::delete('/friends/{user}', [FriendController::class, 'destroy']);

    // Уведомления
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::get('/clans', [ClanController::class, 'index']);
    Route::get('/clans/top', [ClanController::class, 'top']);
    Route::post('/clans', [ClanController::class, 'store']);
    Route::get('/clans/{clan}', [ClanController::class, 'show']);
    Route::match(['put', 'post'], '/clans/{clan}', [ClanController::class, 'update']);
    Route::post('/clans/{clan}/apply', [ClanController::class, 'apply']);
    Route::post('/clans/{clan}/applications/{application}/accept', [ClanController::class, 'acceptApplication']);
    Route::post('/clans/{clan}/applications/{application}/decline', [ClanController::class, 'declineApplication']);
    Route::post('/clans/{clan}/leave', [ClanController::class, 'leave']);
    Route::delete('/clans/{clan}/members/{user}', [ClanController::class, 'kick']);
    Route::get('/clans/{clan}/applications', [ClanController::class, 'applications']);
    Route::post('/clans/{clan}/cover/remove', [ClanController::class, 'removeCover']);
    Route::post('/clans/{clan}/avatar/remove', [ClanController::class, 'removeAvatar']);
    // Мероприятия
    Route::get('/clans/{clan}/events', [ClanEventController::class, 'index']);
    Route::post('/clans/{clan}/events', [ClanEventController::class, 'store']);
    Route::delete('/clans/{clan}/events/{event}', [ClanEventController::class, 'destroy']);

    // Войны
    Route::post('/clans/{clan}/wars', [ClanWarController::class, 'store']);
    Route::post('/wars/{war}/accept', [ClanWarController::class, 'accept']);
    Route::post('/wars/{war}/decline', [ClanWarController::class, 'decline']);
    Route::post('/wars/{war}/complete', [ClanWarController::class, 'complete']);


    Route::get('/top', [\App\Http\Controllers\HomeController::class, 'top']);

});
