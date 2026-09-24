<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\Admin\ClanStatsController;
use App\Http\Controllers\Api\Admin\CommentController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClanEventCommentController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\ClanEventController;
use App\Http\Controllers\ClanWarController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TierTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tester\TierTestController as TesterTierTestController;

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
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/players/{user}/achievements', [AchievementController::class, 'user']);
    // Войны
    Route::post('/clans/{clan}/wars', [ClanWarController::class, 'store']);
    Route::post('/wars/{war}/accept', [ClanWarController::class, 'accept']);
    Route::post('/wars/{war}/decline', [ClanWarController::class, 'decline']);
    Route::post('/wars/{war}/complete', [ClanWarController::class, 'complete']);
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::post('/tournaments/{tournament}/register', [TournamentController::class, 'register']);
    Route::post('/tournaments/{tournament}/withdraw', [TournamentController::class, 'withdraw']);
    Route::middleware('role:moderator,admin')->prefix('admin')->group(function () {
        Route::get('/achievements', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'index']);
        Route::post('/achievements', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'store']);
        Route::put('/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'update']);
        Route::delete('/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'destroy']);

        Route::post('/users/{user}/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'grant']);
        Route::delete('/users/{user}/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'revoke']);
        Route::get('/clans', [\App\Http\Controllers\Api\Admin\ClanController::class, 'index']);
        Route::get('/tournaments', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'index']);
        Route::post('/tournaments', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'store']);
        Route::put('/tournaments/{tournament}', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'update']);
        Route::delete('/tournaments/{tournament}', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'destroy']);

        Route::get('/tournaments/{tournament}/participants', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'participants']);
        Route::post('/tournaments/{tournament}/participants/{participant}/approve', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'approveParticipant']);
        Route::post('/tournaments/{tournament}/participants/{participant}/reject', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'rejectParticipant']);
        Route::post('/tournaments/{tournament}/seeds', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'setSeeds']);

        Route::get('/tournaments/{tournament}/matches', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'matches']);
        Route::post('/tournaments/{tournament}/bracket/generate', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'generateBracket']);
        Route::put('/tournaments/{tournament}/matches/{match}', [\App\Http\Controllers\Api\Admin\TournamentController::class, 'updateMatch']);
        // Статистика клана
        Route::get('/clans/{clan}/stats/logs', [ClanStatsController::class, 'logs']);
        Route::post('/clans/{clan}/stats', [ClanStatsController::class, 'update']);
        Route::put('/clans/{clan}/stats', [ClanStatsController::class, 'set']);

        // Комментарии
        Route::get('/comments', [CommentController::class, 'index']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

        // Клан-посты
        Route::get('/clan-events', [CommentController::class, 'events']);
        Route::delete('/clan-events/{event}', [CommentController::class, 'destroyEvent']);

        // Снять баннер
        Route::post('/clans/{clan}/avatar/remove', [\App\Http\Controllers\Api\Admin\ClanController::class, 'removeAvatar']);
        Route::post('/clans/{clan}/cover/remove', [\App\Http\Controllers\Api\Admin\ClanController::class, 'removeCover']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Юзеры
        Route::get('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users/{user}/ban', [UserController::class, 'ban']);
        Route::post('/users/{user}/unban', [UserController::class, 'unban']);

        // Роли
        Route::post('/users/{user}/role', [\App\Http\Controllers\Api\Admin\RoleController::class, 'update']);

        // Ачивки
        Route::post('/users/{user}/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'grant']);
        Route::delete('/users/{user}/achievements/{achievement}', [\App\Http\Controllers\Api\Admin\AchievementController::class, 'revoke']);

        // Аспекты
        Route::put('/users/{user}/aspects', [\App\Http\Controllers\Api\Admin\AspectController::class, 'update']);

        // Тир-тесты — админ проводит вручную
        Route::post('/users/{user}/tier-test', [\App\Http\Controllers\Api\Admin\AspectController::class, 'conductTierTest']);

        // Кланы
        Route::post('/clans/{clan}/ban', [\App\Http\Controllers\Api\Admin\ClanController::class, 'ban']);
        Route::post('/clans/{clan}/unban', [\App\Http\Controllers\Api\Admin\ClanController::class, 'unban']);
        Route::delete('/clans/{clan}', [\App\Http\Controllers\Api\Admin\ClanController::class, 'destroy']);
    });
    Route::get('/clans/{clan}/events/{event}/comments', [ClanEventCommentController::class, 'index']);
    Route::post('/clans/{clan}/events/{event}/comments', [ClanEventCommentController::class, 'store']);
    Route::delete('/clans/{clan}/events/{event}/comments/{comment}', [ClanEventCommentController::class, 'destroy']);

    Route::get('/top', [\App\Http\Controllers\HomeController::class, 'top']);
    Route::middleware('role:tester,admin')->prefix('tester')->group(function () {
        Route::get('/tier-tests', [TesterTierTestController::class, 'index']);
        Route::get('/tier-tests/stats', [TesterTierTestController::class, 'stats']);
        Route::get('/tier-tests/{tierTest}', [TesterTierTestController::class, 'show']);
        Route::post('/tier-tests/{tierTest}/claim', [TesterTierTestController::class, 'claim']);
        Route::post('/tier-tests/{tierTest}/unclaim', [TesterTierTestController::class, 'unclaim']);
        Route::post('/tier-tests/{tierTest}/complete', [TesterTierTestController::class, 'complete']);
        Route::post('/tier-tests/{tierTest}/cancel', [TesterTierTestController::class, 'cancel']);
    });
});
