<?php

use App\Domains\Players\Controllers\Api\ProfileRecommendationController;
use App\Domains\Players\Controllers\HomeController;
use App\Domains\Players\Controllers\PlayerController;
use App\Domains\Tiers\Controllers\TierTestController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

// === Публичные контроллеры ===
use App\Domains\Auth\Controllers\Api\AuthController;
use App\Domains\Achievements\Controllers\Api\AchievementController;
use App\Domains\Tournaments\Controllers\Api\TournamentController;
use App\Domains\News\Controllers\Api\NewsController;
use App\Domains\Clan\Controllers\Api\ClanEventCommentController;
use App\Domains\Clan\Controllers\ClanController;
use App\Domains\Clan\Controllers\ClanEventController;
use App\Domains\Clan\Controllers\ClanWarController;
use App\Domains\Friends\Controllers\FriendController;
use App\Domains\Notifications\Controllers\NotificationController;

// === Tester ===
use App\Domains\Tiers\Controllers\Api\Tester\TierTestController as TesterTierTestController;
use App\Domains\Bridge\Controllers\Api\BridgeController;
use App\Domains\Bridge\Controllers\Api\Tester\BridgeReviewController;

// === Admin ===
use App\Domains\Users\Controllers\Api\Admin\UserController as AdminUserController;
use App\Domains\Users\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Domains\Achievements\Controllers\Api\Admin\AchievementController as AdminAchievementController;
use App\Domains\Tiers\Controllers\Api\Admin\AspectController as AdminAspectController;
use App\Domains\Clan\Controllers\Api\Admin\ClanController as AdminClanController;
use App\Domains\Clan\Controllers\Api\Admin\ClanStatsController as AdminClanStatsController;
use App\Domains\Clan\Controllers\Api\Admin\CommentController as AdminCommentController;
use App\Domains\Tournaments\Controllers\Api\Admin\TournamentController as AdminTournamentController;
use App\Domains\Core\Controllers\Api\Admin\SiteSettingsController;

// === Магазин и ApexCoin ===
use App\Domains\Shop\Controllers\Api\ShopController;
use App\Domains\Wallet\Controllers\Api\WalletController;
use App\Domains\Wallet\Controllers\Api\GiftController;
use App\Domains\Shop\Controllers\Api\Admin\ShopController as AdminShopController;

// === Форум ===
use App\Domains\Forum\Controllers\Api\ForumController;
use App\Domains\Forum\Controllers\Api\Admin\ForumController as AdminForumController;
use App\Domains\News\Controllers\Api\Admin\NewsController as AdminNewsController;

/*
|--------------------------------------------------------------------------
| ПУБЛИЧНЫЕ РОУТЫ (без авторизации)
|--------------------------------------------------------------------------
*/
use App\Domains\Auth\Controllers\Api\PasswordResetController;

Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:3,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1');
});
Route::prefix('auth')->group(function () {
    // Регистрация (3 шага)
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/register/send-code', [AuthController::class, 'sendVerificationCode'])
        ->middleware('throttle:3,1');

    Route::post('/register/verify-code', [AuthController::class, 'verifyCode'])
        ->middleware('throttle:10,1');

    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect(config('app.frontend_url') . '/profile?verified=1');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

/*
|--------------------------------------------------------------------------
| МАГАЗИН (публично) — apex:shop-public
|--------------------------------------------------------------------------
| Точные пути объявлены раньше параметрического: Laravel берёт первый
| совпавший роут, поэтому '{shopItem}' ушёл бы в конец блока.
*/
Route::get('/shop', [ShopController::class, 'index']);
Route::get('/shop/priority-candidates', [ShopController::class, 'priorityCandidates'])
    ->middleware('auth:sanctum');
Route::get('/shop/inventory', [ShopController::class, 'inventory'])
    ->middleware('auth:sanctum');
Route::get('/shop/{shopItem}', [ShopController::class, 'show'])->whereNumber('shopItem');
Route::get('/players/{user}/recommendations', [ProfileRecommendationController::class, 'index'])
    ->whereNumber('user');

// Home (главная)
Route::get('/home', [HomeController::class, 'index']);
Route::get('/top', [\App\Domains\Players\Controllers\TopController::class, 'index']);

// Новости
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show'])->whereNumber('news');

// Турниры (просмотр)
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->whereNumber('tournament');

// Кланы (просмотр)
Route::get('/clans', [ClanController::class, 'index']);
Route::get('/clans/top', [ClanController::class, 'top']);

// Просмотр клана доступен и гостям: /api/clans/Имя_клана или /api/clans/7
// apex:public-clan-show
Route::get('/clans/{clan}', [ClanController::class, 'show']);

// Список игроков отдаёт рейтинг: /api/ranking (RankingController)

// Рейтинг с курсорной пагинацией — apex:ranking
Route::get('/ranking', [\App\Domains\Players\Controllers\Api\RankingController::class, 'index']);

// Профиль открывается и по id, и по нику: /api/users/Ник
Route::get('/players/{user}', [PlayerController::class, 'show']);
Route::get('/users/{user}', [PlayerController::class, 'show']);

/*
|--------------------------------------------------------------------------
| АВТОРИЗОВАННЫЕ РОУТЫ
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ФОРУМ (публично) — apex:forum-public
|--------------------------------------------------------------------------
*/
Route::prefix('forum')->group(function () {
    Route::get('/', [ForumController::class, 'index']);
    Route::get('/topics', [ForumController::class, 'topics']);
    Route::get('/search', [ForumController::class, 'search']);
    Route::get('/topics/{topic}', [ForumController::class, 'show'])->whereNumber('topic');
    Route::get('/attachments/{attachment}/download', [ForumController::class, 'download'])->whereNumber('attachment');
});
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email уже подтверждён.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Письмо отправлено повторно.']);
    })->middleware('throttle:6,1');

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

    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'index']);
        Route::get('/conversations/{conversation}', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'show'])
            ->whereNumber('conversation');
        Route::get('/search', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'search']);
        Route::post('/start-direct', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'startDirect']);
        Route::post('/start-clan', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'startClan']);
        Route::post('/messages/{message}/read', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'markRead'])
            ->whereNumber('message');

        Route::post('/messages/{message}/forward', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'forward'])
            ->whereNumber('message');
        Route::post('/conversations/{conversation}/messages', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'send'])
            ->whereNumber('conversation');

        Route::get('/unread-count', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'unreadCount']);

        // --- ПРИСУТСТВИЕ: кто онлайн — apex:presence ---
        Route::get('/presence', [\App\Domains\Chat\Controllers\Api\PresenceController::class, 'index']);
        Route::post('/presence/online', [\App\Domains\Chat\Controllers\Api\PresenceController::class, 'online']);
        Route::post('/presence/offline', [\App\Domains\Chat\Controllers\Api\PresenceController::class, 'offline']);

        // --- ПОИСК ПО СООБЩЕНИЯМ ДИАЛОГА — apex:chat-search ---
        Route::get('/conversations/{conversation}/search', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'searchMessages'])
            ->whereNumber('conversation');

        // --- ВЛОЖЕНИЯ В ЧАТЕ — apex:chat-attachments ---
        Route::post('/attachments', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'uploadAttachment'])
            ->middleware('throttle:40,1');
        Route::get('/attachments/{attachment}/download', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'downloadAttachment'])
            ->whereNumber('attachment');
        Route::post('/conversations/{conversation}/read', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'markConversationRead'])
            ->whereNumber('conversation');
        Route::post('/conversations/{conversation}/typing', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'typing'])
            ->whereNumber('conversation');
        Route::put('/messages/{message}', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'update'])
            ->whereNumber('message');
        Route::delete('/messages/{message}', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'destroy'])
            ->whereNumber('message');
    });

    /*
    |----------------------------------------------------------------------
    | ФОРУМ: ДЕЙСТВИЯ — apex:forum-actions
    |----------------------------------------------------------------------
    */
    Route::prefix('forum')->group(function () {
        Route::post('/topics', [ForumController::class, 'store']);
        Route::put('/topics/{topic}', [ForumController::class, 'update'])->whereNumber('topic');
        Route::delete('/topics/{topic}', [ForumController::class, 'destroy'])->whereNumber('topic');
        Route::post('/topics/{topic}/reply', [ForumController::class, 'reply'])->whereNumber('topic');

        Route::put('/replies/{reply}', [ForumController::class, 'updateReply'])->whereNumber('reply');
        Route::delete('/replies/{reply}', [ForumController::class, 'destroyReply'])->whereNumber('reply');

        Route::post('/like/{type}/{id}', [ForumController::class, 'like'])
            ->whereIn('type', ['topic', 'reply'])
            ->whereNumber('id');

        Route::post('/upload', [ForumController::class, 'upload'])
            ->middleware('throttle:30,1');
    });
    Route::post('/players/{user}/recommendations', [ProfileRecommendationController::class, 'store'])
        ->whereNumber('user');

    Route::delete('/players/{user}/recommendations', [ProfileRecommendationController::class, 'destroy'])
        ->whereNumber('user');

    Route::post('/recommendations/{recommendation}/hide', [ProfileRecommendationController::class, 'hide'])
        ->whereNumber('recommendation');

    /*
    |----------------------------------------------------------------------
    | ИГРОКИ
    |----------------------------------------------------------------------
    */

    /*
    |----------------------------------------------------------------------
    | APEXCOIN: КОШЕЛЁК — apex:shop-wallet
    |----------------------------------------------------------------------
    */
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'index']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::post('/daily-bonus', [WalletController::class, 'claimDaily']);
    });

    /*
    |----------------------------------------------------------------------
    | МАГАЗИН: ПОКУПКА И НАДЕВАНИЕ — apex:shop-buy
    |----------------------------------------------------------------------
    */
    Route::prefix('shop')->group(function () {
        Route::post('/{shopItem}/purchase', [ShopController::class, 'purchase'])->whereNumber('shopItem');
        Route::post('/{shopItem}/equip', [ShopController::class, 'equip'])->whereNumber('shopItem');
        Route::post('/{shopItem}/unequip', [ShopController::class, 'unequip'])->whereNumber('shopItem');
    });

    /*
    |----------------------------------------------------------------------
    | ПРИМЕНЕНИЕ КУПЛЕННОГО ПРИОРИТЕТА — apex:shop-priority
    |----------------------------------------------------------------------
    */
    Route::post('/tier-tests/{tierTest}/priority', [ShopController::class, 'applyPriority'])
        ->whereNumber('tierTest');

    /*
    |----------------------------------------------------------------------
    | ПОДАРКИ ВАЛЮТЫ ДРУЗЬЯМ — apex:shop-gifts
    |----------------------------------------------------------------------
    */
    Route::prefix('gifts')->group(function () {
        Route::get('/limits', [GiftController::class, 'limits']);
        Route::post('/{user}', [GiftController::class, 'store'])->whereNumber('user');
    });
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
        Route::post('/{tierTest}/cancel', [TierTestController::class, 'cancel'])->whereNumber('tierTest');
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

        // Просмотр клана вынесен в публичные маршруты (доступен гостям)
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
        Route::get('/{war}', [ClanWarController::class, 'show'])->whereNumber('war');
        Route::post('/{war}/join', [ClanWarController::class, 'join'])->whereNumber('war');
        Route::post('/{war}/leave', [ClanWarController::class, 'leave'])->whereNumber('war');
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
    | БРИДЖ — заявки игрока на виды бриджа
    |----------------------------------------------------------------------
    |
    | Записи в очередь нет: игрок отмечает вид и прикладывает видео,
    | проверяет его бридж-тестер.
    */
    Route::prefix('bridge')->group(function () {
        Route::get('/techniques', [BridgeController::class, 'index']);
        Route::post('/techniques', [BridgeController::class, 'store']);
        Route::delete('/submissions/{submission}', [BridgeController::class, 'destroy'])
            ->whereNumber('submission');
    });

    /*
    |----------------------------------------------------------------------
    | БРИДЖ-ТЕСТЕР (role: bridge_tester, admin)
    |----------------------------------------------------------------------
    |
    | Раздел видят только эти роли, поэтому остальные игроки его не видят.
    */
    Route::middleware('role:bridge_tester,admin')->prefix('bridge-review')->group(function () {
        Route::get('/submissions', [BridgeReviewController::class, 'index']);
        Route::post('/submissions/{submission}', [BridgeReviewController::class, 'review'])
            ->whereNumber('submission');
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

        // --- МАГАЗИН: КАТАЛОГ И НАГРАДЫ — apex:shop-admin ---
        Route::get('/shop-items', [AdminShopController::class, 'index']);
        Route::post('/shop-items', [AdminShopController::class, 'store']);
        Route::put('/shop-items/{shopItem}', [AdminShopController::class, 'update'])->whereNumber('shopItem');
        Route::delete('/shop-items/{shopItem}', [AdminShopController::class, 'destroy'])->whereNumber('shopItem');
        Route::post('/shop-items/{shopItem}/toggle', [AdminShopController::class, 'toggle'])->whereNumber('shopItem');
        Route::get('/shop-rewards', [AdminShopController::class, 'rewards']);
        Route::put('/shop-rewards', [AdminShopController::class, 'updateRewards']);
        Route::get('/clans', [AdminClanController::class, 'index']);
        Route::post('/clans/{clan}/stats', [AdminClanStatsController::class, 'update'])->whereNumber('clan');
        Route::put('/clans/{clan}/stats', [AdminClanStatsController::class, 'set'])->whereNumber('clan');
        Route::get('/clans/{clan}/stats/logs', [AdminClanStatsController::class, 'logs'])->whereNumber('clan');
        Route::post('/clans/{clan}/avatar/remove', [AdminClanController::class, 'removeAvatar'])->whereNumber('clan');
        Route::post('/clans/{clan}/cover/remove', [AdminClanController::class, 'removeCover'])->whereNumber('clan');

        // --- ФОРУМ: МОДЕРАЦИЯ — apex:forum-admin ---
        Route::get('/forum/stats', [AdminForumController::class, 'stats']);
        Route::get('/forum/categories', [AdminForumController::class, 'categories']);
        Route::post('/forum/categories', [AdminForumController::class, 'storeCategory']);
        Route::put('/forum/categories/{category}', [AdminForumController::class, 'updateCategory'])->whereNumber('category');
        Route::delete('/forum/categories/{category}', [AdminForumController::class, 'destroyCategory'])->whereNumber('category');

        Route::get('/forum/topics', [AdminForumController::class, 'topics']);
        Route::post('/forum/topics/{topic}/pin', [AdminForumController::class, 'pin'])->whereNumber('topic');
        Route::post('/forum/topics/{topic}/lock', [AdminForumController::class, 'lock'])->whereNumber('topic');
        Route::delete('/forum/topics/{topic}', [AdminForumController::class, 'destroyTopic'])->whereNumber('topic');
        Route::post('/forum/topics/{topic}/restore', [AdminForumController::class, 'restoreTopic'])->whereNumber('topic');
        Route::delete('/forum/replies/{reply}', [AdminForumController::class, 'destroyReply'])->whereNumber('reply');
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
    
        // --- APEXCOIN: НАЧИСЛЕНИЯ И ЛЕДЖЕР — apex:shop-coins ---
        Route::post('/users/{user}/coins', [AdminShopController::class, 'grantCoins'])->whereNumber('user');
        Route::get('/coin-transactions', [AdminShopController::class, 'transactions']);
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
    Route::get('/', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'index']);

    // Форум
    Route::get('/forum', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'index']);
    Route::post('/forum', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'store'])
        ->middleware('clan.member:forum');
    Route::get('/forum/{topic}', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'show']);
    Route::post('/forum/{topic}/reply', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'reply']);
    Route::post('/forum/{topic}/pin', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'pin'])
        ->middleware('clan.member:forum');
    Route::post('/forum/{topic}/lock', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'lock'])
        ->middleware('clan.member:forum');
    Route::delete('/forum/{topic}', [\App\Domains\Clan\Controllers\Api\ClanForumController::class, 'destroy']);

    // Ресурсы клана.
    // Всем действиям нужен контекст клана, иначе контроллер не знал,
    // к какому клану относится ресурс, и отдавал 404 вместо 403.
    // Ресурсы клана (группа уже под clan.member — контекст клана доступен)
    Route::get('/resources', [\App\Domains\Clan\Controllers\Api\ClanResourceController::class, 'index']);
    Route::post('/resources', [\App\Domains\Clan\Controllers\Api\ClanResourceController::class, 'store'])
        ->middleware('clan.member:resources');
    Route::post('/resources/{resource}/download', [\App\Domains\Clan\Controllers\Api\ClanResourceController::class, 'download']);
    Route::delete('/resources/{resource}', [\App\Domains\Clan\Controllers\Api\ClanResourceController::class, 'destroy']);
    Route::post('/clans/{clan}/wars', [ClanWarController::class, 'store']);
    Route::post('/wars/{war}/accept', [ClanWarController::class, 'accept']);
    Route::post('/wars/{war}/decline', [ClanWarController::class, 'decline']);
    Route::post('/wars/{war}/complete', [ClanWarController::class, 'complete']);
    // Роли
    Route::post('/roles/{user}', [\App\Domains\Clan\Controllers\Api\ClanRoleController::class, 'update']);
    Route::delete('/members/{user}', [\App\Domains\Clan\Controllers\Api\ClanRoleController::class, 'kick']);
    Route::post('/transfer/{user}', [\App\Domains\Clan\Controllers\Api\ClanRoleController::class, 'transferLeadership']);

});
