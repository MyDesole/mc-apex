<?php

use App\Http\Controllers\Api\AuthController;
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
});
