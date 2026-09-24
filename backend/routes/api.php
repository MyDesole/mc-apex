<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TierTestController;
use Illuminate\Support\Facades\Route;

// === Публичные контроллеры ===
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ClanEventCommentController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\ClanEventController;
use App\Http\Controllers\ClanWarController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\NotificationController;

// === Tester ===
use App\Http\Controllers\Api\Tester\TierTestController as TesterTierTestController;

// === Admin ===
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\AchievementController as AdminAchievementController;
use App\Http\Controllers\Api\Admin\AspectController as AdminAspectController;
use App\Http\Controllers\Api\Admin\ClanController as AdminClanController;
use App\Http\Controllers\Api\Admin\ClanStatsController as AdminClanStatsController;
use App\Http\Controllers\Api\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Api\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Api\Admin\SiteSettingsController;
use App\Http\Controllers\Api\Admin\NewsController as AdminNewsController;

/*
|--------------------------------------------------------------------------
| ПУБЛИЧНЫЕ РОУТЫ (без авторизации)
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Home (главная)
Route::get('/home', [HomeController::class, 'index']);
Route::get('/top', [HomeController::class, 'top']);

// Новости
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show'])->whereNumber('news');

// Турниры (просмотр)
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->whereNumber('tournament');

// Кланы (просмотр)
Route::get('/clans', [ClanController::class, 'index']);
Route::get('/clans/top', [ClanController::class, 'top']);

// Игроки (просмотр)
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{user}', [PlayerController::class, 'show'])->whereNumber('user');

/*
|--------------------------------------------------------------------------
| АВТОРИЗОВАННЫЕ РОУТЫ
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------------------------------------------------------
    | AUTH
    |----------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    /*
    |----------------------------------------------------------------------
    | ПРОФИЛЬ (я)
    |----------------------------------------------------------------------
    */
    Route::prefix('players/me')->group(function () {
        Route::match(['put', 'post'], '/', [PlayerController::class, 'updateMe']);
        Route::match(['put', 'post'], '/profile', [PlayerController::class, 'updateProfile']);
        Route::put('/aspects', [PlayerController::class, 'updateAspects']);

        Route::post('/avatar/remove', [PlayerController::class, 'removeAvatar']);
        Route::post('/cover/remove', [PlayerController::class, 'removeCover']);
        Route::post('/card-background/remove', [PlayerController::class, 'removeCardBackground']);
    });

    /*
    |----------------------------------------------------------------------
    | ИГРОКИ
    |----------------------------------------------------------------------
    */
    Route::get('/players/{user}/achievements', [AchievementController::class, 'user'])->whereNumber('user');
    Route::get('/players/{user}/tier-history', [TierTestController::class, 'history'])->whereNumber('user');

    /*
    |----------------------------------------------------------------------
    | АЧИВКИ (мои)
    |----------------------------------------------------------------------
    */
    Route::get('/achievements', [AchievementController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | ТИР-ТЕСТЫ
    |----------------------------------------------------------------------
    */
    Route::prefix('tier-tests')->group(function () {
        Route::get('/', [TierTestController::class, 'index']);
        Route::post('/', [TierTestController::class, 'store']);
        Route::get('/{tierTest}', [TierTestController::class, 'show'])->whereNumber('tierTest');
        Route::put('/{tierTest}', [TierTestController::class, 'update'])->whereNumber('tierTest');
    });

    /*
    |----------------------------------------------------------------------
    | ДРУЗЬЯ
    |----------------------------------------------------------------------
    */
    Route::prefix('friends')->group(function () {
        Route::get('/', [FriendController::class, 'index']);
        Route::post('/{user}', [FriendController::class, 'store'])->whereNumber('user');
        Route::post('/{user}/accept', [FriendController::class, 'accept'])->whereNumber('user');
        Route::delete('/{user}', [FriendController::class, 'destroy'])->whereNumber('user');
    });

    /*
    |----------------------------------------------------------------------
    | УВЕДОМЛЕНИЯ
    |----------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    });

    /*
    |----------------------------------------------------------------------
    | КЛАНЫ (авторизованные действия)
    |----------------------------------------------------------------------
    */
    Route::prefix('clans')->group(function () {
        // Создание
        Route::post('/', [ClanController::class, 'store']);

        // Просмотр (динамический — в конце!)
        Route::get('/{clan}', [ClanController::class, 'show'])->whereNumber('clan');
        Route::match(['put', 'post'], '/{clan}', [ClanController::class, 'update'])->whereNumber('clan');

        // Заявки
        Route::post('/{clan}/apply', [ClanController::class, 'apply'])->whereNumber('clan');
        Route::get('/{clan}/applications', [ClanController::class, 'applications'])->whereNumber('clan');
        Route::post('/{clan}/applications/{application}/accept', [ClanController::class, 'acceptApplication']);
        Route::post('/{clan}/applications/{application}/decline', [ClanController::class, 'declineApplication']);

        // Участники
        Route::post('/{clan}/leave', [ClanController::class, 'leave'])->whereNumber('clan');
        Route::delete('/{clan}/members/{user}', [ClanController::class, 'kick'])->whereNumber('clan');

        // Медиа
        Route::post('/{clan}/cover/remove', [ClanController::class, 'removeCover'])->whereNumber('clan');
        Route::post('/{clan}/avatar/remove', [ClanController::class, 'removeAvatar'])->whereNumber('clan');

        // Мероприятия
        Route::get('/{clan}/events', [ClanEventController::class, 'index'])->whereNumber('clan');
        Route::post('/{clan}/events', [ClanEventController::class, 'store'])->whereNumber('clan');
        Route::delete('/{clan}/events/{event}', [ClanEventController::class, 'destroy']);

        // Войны
        Route::post('/{clan}/wars', [ClanWarController::class, 'store'])->whereNumber('clan');
    });

    /*
    |----------------------------------------------------------------------
    | ВОЙНЫ (действия)
    |----------------------------------------------------------------------
    */
    Route::prefix('wars')->group(function () {
        Route::post('/{war}/accept', [ClanWarController::class, 'accept'])->whereNumber('war');
        Route::post('/{war}/decline', [ClanWarController::class, 'decline'])->whereNumber('war');
        Route::post('/{war}/complete', [ClanWarController::class, 'complete'])->whereNumber('war');
    });

    /*
    |----------------------------------------------------------------------
    | КОММЕНТАРИИ К КЛАН-ПОСТАМ
    |----------------------------------------------------------------------
    */
    Route::prefix('clans/{clan}/events/{event}/comments')->group(function () {
        Route::get('/', [ClanEventCommentController::class, 'index']);
        Route::post('/', [ClanEventCommentController::class, 'store']);
        Route::delete('/{comment}', [ClanEventCommentController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | ТУРНИРЫ (действия)
    |----------------------------------------------------------------------
    */
    Route::prefix('tournaments')->group(function () {
        Route::post('/{tournament}/register', [TournamentController::class, 'register'])->whereNumber('tournament');
        Route::post('/{tournament}/withdraw', [TournamentController::class, 'withdraw'])->whereNumber('tournament');
    });

    /*
    |----------------------------------------------------------------------
    | ТЕСТЕР (role: tester, admin)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:tester,admin')->prefix('tester')->group(function () {
        Route::get('/tier-tests', [TesterTierTestController::class, 'index']);
        Route::get('/tier-tests/stats', [TesterTierTestController::class, 'stats']);
        Route::get('/tier-tests/{tierTest}', [TesterTierTestController::class, 'show'])->whereNumber('tierTest');
        Route::post('/tier-tests/{tierTest}/claim', [TesterTierTestController::class, 'claim'])->whereNumber('tierTest');
        Route::post('/tier-tests/{tierTest}/unclaim', [TesterTierTestController::class, 'unclaim'])->whereNumber('tierTest');
        Route::post('/tier-tests/{tierTest}/complete', [TesterTierTestController::class, 'complete'])->whereNumber('tierTest');
        Route::post('/tier-tests/{tierTest}/cancel', [TesterTierTestController::class, 'cancel'])->whereNumber('tierTest');
    });

    /*
    |----------------------------------------------------------------------
    | АДМИНКА — МОДЕРАТОР + АДМИН (role: moderator, admin)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:moderator,admin')->prefix('admin')->group(function () {

        // --- КЛАНЫ ---
        Route::get('/clans', [AdminClanController::class, 'index']);
        Route::post('/clans/{clan}/stats', [AdminClanStatsController::class, 'update'])->whereNumber('clan');
        Route::put('/clans/{clan}/stats', [AdminClanStatsController::class, 'set'])->whereNumber('clan');
        Route::get('/clans/{clan}/stats/logs', [AdminClanStatsController::class, 'logs'])->whereNumber('clan');
        Route::post('/clans/{clan}/avatar/remove', [AdminClanController::class, 'removeAvatar'])->whereNumber('clan');
        Route::post('/clans/{clan}/cover/remove', [AdminClanController::class, 'removeCover'])->whereNumber('clan');

        // --- КОММЕНТАРИИ ---
        Route::get('/comments', [AdminCommentController::class, 'index']);
        Route::delete('/comments/{comment}', [AdminCommentController::class, 'destroy'])->whereNumber('comment');

        // --- КЛАН-ПОСТЫ ---
        Route::get('/clan-events', [AdminCommentController::class, 'events']);
        Route::delete('/clan-events/{event}', [AdminCommentController::class, 'destroyEvent'])->whereNumber('event');

        // --- ТУРНИРЫ ---
        Route::get('/tournaments', [AdminTournamentController::class, 'index']);
        Route::post('/tournaments', [AdminTournamentController::class, 'store']);
        Route::match(['put', 'post'], '/tournaments/{tournament}', [AdminTournamentController::class, 'update'])->whereNumber('tournament');
        Route::delete('/tournaments/{tournament}', [AdminTournamentController::class, 'destroy'])->whereNumber('tournament');

        Route::get('/tournaments/{tournament}/participants', [AdminTournamentController::class, 'participants'])->whereNumber('tournament');
        Route::post('/tournaments/{tournament}/participants/{participant}/approve', [AdminTournamentController::class, 'approveParticipant']);
        Route::post('/tournaments/{tournament}/participants/{participant}/reject', [AdminTournamentController::class, 'rejectParticipant']);
        Route::post('/tournaments/{tournament}/seeds', [AdminTournamentController::class, 'setSeeds'])->whereNumber('tournament');

        Route::get('/tournaments/{tournament}/matches', [AdminTournamentController::class, 'matches'])->whereNumber('tournament');
        Route::post('/tournaments/{tournament}/bracket/generate', [AdminTournamentController::class, 'generateBracket'])->whereNumber('tournament');
        Route::put('/tournaments/{tournament}/matches/{match}', [AdminTournamentController::class, 'updateMatch']);

        // --- АЧИВКИ (просмотр модератором) ---
        Route::get('/achievements', [AdminAchievementController::class, 'index']);
        Route::post('/achievements', [AdminAchievementController::class, 'store']);
        Route::put('/achievements/{achievement}', [AdminAchievementController::class, 'update'])->whereNumber('achievement');
        Route::delete('/achievements/{achievement}', [AdminAchievementController::class, 'destroy'])->whereNumber('achievement');
    });

    /*
    |----------------------------------------------------------------------
    | АДМИНКА — ТОЛЬКО АДМИН (role: admin)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // --- ЮЗЕРЫ ---
        // СНАЧАЛА статические!
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/verified', [AdminUserController::class, 'verified']);

        // ПОТОМ динамические
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->whereNumber('user');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->whereNumber('user');
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->whereNumber('user');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify'])->whereNumber('user');
        Route::post('/users/{user}/unverify', [AdminUserController::class, 'unverify'])->whereNumber('user');
        Route::post('/users/{user}/role', [AdminRoleController::class, 'update'])->whereNumber('user');

        // --- АЧИВКИ ---
        Route::post('/users/{user}/achievements/{achievement}', [AdminAchievementController::class, 'grant']);
        Route::delete('/users/{user}/achievements/{achievement}', [AdminAchievementController::class, 'revoke']);

        // --- АСПЕКТЫ ---
        Route::put('/users/{user}/aspects', [AdminAspectController::class, 'update'])->whereNumber('user');
        Route::post('/users/{user}/tier-test', [AdminAspectController::class, 'conductTierTest'])->whereNumber('user');

        // --- КЛАНЫ (бан/удаление) ---
        Route::post('/clans/{clan}/ban', [AdminClanController::class, 'ban'])->whereNumber('clan');
        Route::post('/clans/{clan}/unban', [AdminClanController::class, 'unban'])->whereNumber('clan');
        Route::delete('/clans/{clan}', [AdminClanController::class, 'destroy'])->whereNumber('clan');

        // --- ГЛАВНАЯ ---
        Route::get('/site-settings', [SiteSettingsController::class, 'index']);
        Route::put('/site-settings', [SiteSettingsController::class, 'update']);

        // --- НОВОСТИ ---
        Route::get('/news', [AdminNewsController::class, 'index']);
        Route::post('/news', [AdminNewsController::class, 'store']);
        Route::match(['put', 'post'], '/news/{news}', [AdminNewsController::class, 'update'])->whereNumber('news');
        Route::delete('/news/{news}', [AdminNewsController::class, 'destroy'])->whereNumber('news');
    });
});

Route::middleware(['auth:sanctum', 'clan.member'])->prefix('my-clan')->group(function () {
    // Дашборд
    Route::get('/', [\App\Http\Controllers\Api\MyClanController::class, 'index']);

    // Форум
    Route::get('/forum', [\App\Http\Controllers\Api\ClanForumController::class, 'index']);
    Route::post('/forum', [\App\Http\Controllers\Api\ClanForumController::class, 'store'])
        ->middleware('clan.member:forum');
    Route::get('/forum/{topic}', [\App\Http\Controllers\Api\ClanForumController::class, 'show']);
    Route::post('/forum/{topic}/reply', [\App\Http\Controllers\Api\ClanForumController::class, 'reply']);
    Route::post('/forum/{topic}/pin', [\App\Http\Controllers\Api\ClanForumController::class, 'pin'])
        ->middleware('clan.member:forum');
    Route::post('/forum/{topic}/lock', [\App\Http\Controllers\Api\ClanForumController::class, 'lock'])
        ->middleware('clan.member:forum');
    Route::delete('/forum/{topic}', [\App\Http\Controllers\Api\ClanForumController::class, 'destroy']);

    // Ресурсы
    Route::get('/resources', [\App\Http\Controllers\Api\ClanResourceController::class, 'index']);
    Route::post('/resources', [\App\Http\Controllers\Api\ClanResourceController::class, 'store'])
        ->middleware('clan.member:resources');
    Route::post('/resources/{resource}/download', [\App\Http\Controllers\Api\ClanResourceController::class, 'download']);
    Route::delete('/resources/{resource}', [\App\Http\Controllers\Api\ClanResourceController::class, 'destroy']);

    // Роли
    Route::post('/roles/{user}', [\App\Http\Controllers\Api\ClanRoleController::class, 'update']);
    Route::delete('/members/{user}', [\App\Http\Controllers\Api\ClanRoleController::class, 'kick']);
    Route::post('/transfer/{user}', [\App\Http\Controllers\Api\ClanRoleController::class, 'transferLeadership']);
});
