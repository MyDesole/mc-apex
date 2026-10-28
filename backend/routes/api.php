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
use App\Domains\Bridge\Controllers\Api\Curator\BridgeTechniqueController as CuratorBridgeTechniqueController;
use App\Domains\Bridge\Controllers\Api\BridgeVideoController;
use App\Domains\Minecraft\Controllers\Api\MinecraftServerController;
use App\Domains\Minecraft\Controllers\Api\MinecraftLinkController;
use App\Domains\Minecraft\Controllers\Api\MinecraftSkinController;
use App\Domains\Search\Controllers\Api\SearchController;
use App\Domains\Activity\Controllers\Api\ActivityController;

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

    // Выключенная регистрация останавливает уже на первом шаге — до письма с кодом
    Route::post('/register/send-code', [AuthController::class, 'sendVerificationCode'])
        ->middleware(['throttle:3,1', 'feature:registration']);

    Route::post('/register/verify-code', [AuthController::class, 'verifyCode'])
        ->middleware('throttle:10,1');

    Route::post('/register', [AuthController::class, 'register'])->middleware('feature:registration');
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

// Гостевая книга профиля: читать могут все
Route::get('/players/{user}/guestbook', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'guestbook'])
    ->whereNumber('user');

// Респекты профиля и топ самых уважаемых недели — видны всем
Route::get('/players/{user}/respects', [\App\Domains\Players\Controllers\Api\RespectController::class, 'show'])
    ->whereNumber('user');
Route::get('/respects/top', [\App\Domains\Players\Controllers\Api\RespectController::class, 'top']);

// Опрос в профиле: результаты видны всем
Route::get('/players/{user}/polls/{block}', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'poll'])
    ->whereNumber('user')
    ->where('block', '[a-z0-9]{1,16}');

// Питомец профиля: состояние видно всем (до /players/{user}, иначе перехватит)
Route::get('/players/{user}/pet', [\App\Domains\Players\Controllers\Api\PetController::class, 'show'])
    ->whereNumber('user');

// Карточки тиммейтов для блока профиля (до /players/{user}, иначе перехватит)
Route::get('/players/teammates', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'teammates'])
    ->middleware('throttle:60,1');

// Аукцион питомцев: лоты видны и гостям
Route::get('/auctions', [\App\Domains\Shop\Controllers\Api\PetAuctionController::class, 'index']);
Route::get('/auctions/{auction}', [\App\Domains\Shop\Controllers\Api\PetAuctionController::class, 'show'])->whereNumber('auction');

// Кейсы с косметикой: витрина видна и гостям
Route::get('/cases', [\App\Domains\Shop\Controllers\Api\LootCaseController::class, 'index']);
Route::get('/cases/status', [\App\Domains\Shop\Controllers\Api\LootCaseController::class, 'status']);
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

// Скин из Minecraft по привязанному нику — для 3D-модели и карточки игрока
Route::get('/players/{user}/skin', [MinecraftSkinController::class, 'show'])
    ->middleware('throttle:60,1');

// Командная палитра (Ctrl+K): поиск по игрокам, кланам, форуму и новостям
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:90,1');

// Лента «Сейчас на APEX»: новые события приходят по публичному каналу activity
Route::get('/activity', [ActivityController::class, 'index']);

// Публичная статистика проекта
Route::get('/stats', [\App\Domains\Players\Controllers\Api\StatsController::class, 'index']);

// Чейнджлог и роадмап — публичные страницы
Route::get('/changelog', [\App\Domains\Project\Controllers\ProjectController::class, 'changelog']);
Route::get('/changelog/{slug}', [\App\Domains\Project\Controllers\ProjectController::class, 'entry'])->where('slug', '[a-z0-9-]+');
Route::get('/roadmap', [\App\Domains\Project\Controllers\ProjectController::class, 'roadmap']);

// Сезоны рейтинга
Route::get('/seasons', [\App\Domains\Seasons\Controllers\SeasonController::class, 'index']);
Route::get('/seasons/{season}', [\App\Domains\Seasons\Controllers\SeasonController::class, 'show'])->whereNumber('season');
Route::get('/players/{user}/seasons', [\App\Domains\Seasons\Controllers\SeasonController::class, 'player'])->whereNumber('user');

// Подвал, статус Minecraft-сервера и команда проекта
Route::get('/site', [\App\Domains\Core\Controllers\Api\SiteController::class, 'index']);
Route::get('/site/server-status', [\App\Domains\Core\Controllers\Api\SiteController::class, 'serverStatus'])
    ->middleware('throttle:60,1');
Route::get('/site/team', [\App\Domains\Core\Controllers\Api\SiteController::class, 'team']);

/*
|--------------------------------------------------------------------------
| МАЙНКРАФТ-СЕРВЕР
|--------------------------------------------------------------------------
|
| Сюда ходит плагин, а не браузер. Доступ по общему секрету в заголовке
| X-Minecraft-Server-Key, поэтому группа вынесена из-под сессий.
*/
Route::middleware('minecraft.server')->prefix('minecraft')->group(function () {
    // Заявлен ли ник и на какой аккаунт — по этому плагин решает, что делать
    Route::post('/nickname/resolve', [MinecraftServerController::class, 'resolve']);

    Route::post('/auth/login', [MinecraftServerController::class, 'login']);
    Route::post('/profile', [MinecraftServerController::class, 'profile']);

    // Инструменты админа: на какой аккаунт заявлен ник и как его освободить
    Route::post('/admin/lookup', [MinecraftServerController::class, 'lookup']);
    Route::post('/admin/unlink', [MinecraftServerController::class, 'unlink']);
});

/*
 * Видео бридж-заявки. Лежит в закрытом хранилище, поэтому доступ только по
 * подписанной ссылке с ограниченным сроком — прямого адреса к файлу нет.
 */
Route::get('/bridge/videos/{submission}', [BridgeVideoController::class, 'show'])
    ->middleware('signed')
    ->name('bridge.video')
    ->whereNumber('submission');
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

        // Конструктор профиля: порядок, ширина и настройки блоков
        Route::put('/blocks', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'update'])
            ->middleware('throttle:30,1');

        /*
        |------------------------------------------------------------------
        | Привязка майнкрафта: игрок вводит код, полученный в игре
        |------------------------------------------------------------------
        */
        Route::get('/minecraft/link', [MinecraftLinkController::class, 'show']);
        Route::post('/minecraft/link', [MinecraftLinkController::class, 'store']);
        Route::delete('/minecraft/link', [MinecraftLinkController::class, 'destroy']);
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
        // Реакции и закреп
        Route::post('/messages/{message}/reactions', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'react'])
            ->whereNumber('message')
            ->middleware('throttle:60,1');
        Route::post('/conversations/{conversation}/pin', [\App\Domains\Chat\Controllers\Api\ChatController::class, 'pin'])
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
        Route::post('/topics', [ForumController::class, 'store'])->middleware('feature:forum_posting');
        Route::put('/topics/{topic}', [ForumController::class, 'update'])->whereNumber('topic');
        Route::delete('/topics/{topic}', [ForumController::class, 'destroy'])->whereNumber('topic');
        Route::post('/topics/{topic}/reply', [ForumController::class, 'reply'])->whereNumber('topic')->middleware('feature:forum_posting');

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
    // Мини-игра «Шахтёр»
    Route::get('/games/miner', [\App\Domains\Games\Controllers\MinerController::class, 'show']);
    Route::post('/games/miner/start', [\App\Domains\Games\Controllers\MinerController::class, 'start'])->middleware('throttle:20,1');
    Route::post('/games/miner/dig', [\App\Domains\Games\Controllers\MinerController::class, 'dig'])->middleware('throttle:120,1');
    Route::post('/games/miner/cash-out', [\App\Domains\Games\Controllers\MinerController::class, 'cashOut'])->middleware('throttle:20,1');

    Route::prefix('shop')->group(function () {
        Route::post('/{shopItem}/purchase', [ShopController::class, 'purchase'])->whereNumber('shopItem')->middleware('feature:shop');
        Route::post('/cases/{slug}/open', [\App\Domains\Shop\Controllers\Api\LootCaseController::class, 'open'])
            ->where('slug', '[a-z_]+')
            ->middleware(['feature:shop', 'throttle:20,1']);
        Route::post('/{shopItem}/equip', [ShopController::class, 'equip'])->whereNumber('shopItem');
        Route::post('/{shopItem}/equip-bundle', [ShopController::class, 'equipBundle'])->whereNumber('shopItem');
        Route::post('/randomize', [ShopController::class, 'randomize'])->middleware('throttle:30,1');
        // Конкретный экземпляр питомца: надеть (до трёх) или снять
        Route::post('/pets/{row}/{action}', [ShopController::class, 'togglePet'])
            ->whereNumber('row')
            ->whereIn('action', ['equip', 'unequip']);
        Route::get('/stickers', [ShopController::class, 'stickers']);
        Route::post('/{shopItem}/unequip', [ShopController::class, 'unequip'])->whereNumber('shopItem');
        // Аукцион питомцев: выставить, ставка, снять лот без ставок
        Route::post('/auctions', [\App\Domains\Shop\Controllers\Api\PetAuctionController::class, 'store'])
            ->middleware(['feature:shop', 'throttle:10,1']);
        Route::post('/auctions/{auction}/bid', [\App\Domains\Shop\Controllers\Api\PetAuctionController::class, 'bid'])
            ->whereNumber('auction')->middleware(['feature:shop', 'throttle:30,1']);
        Route::post('/auctions/{auction}/cancel', [\App\Domains\Shop\Controllers\Api\PetAuctionController::class, 'cancel'])
            ->whereNumber('auction')->middleware(['feature:shop', 'throttle:10,1']);
        // Продать магазину или подарить другу (по строке инвентаря)
        Route::post('/inventory/{row}/sell', [\App\Domains\Shop\Controllers\Api\InventoryTradeController::class, 'sell'])
            ->whereNumber('row')->middleware(['feature:shop', 'throttle:30,1']);
        Route::post('/inventory/{row}/gift', [\App\Domains\Shop\Controllers\Api\InventoryTradeController::class, 'gift'])
            ->whereNumber('row')->middleware(['feature:shop', 'throttle:20,1']);
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

    // Гостевая книга профиля: писать — только авторизованным
    // Респект (раз в день) и отметка «заходил в профиль»
    Route::post('/players/{user}/respects', [\App\Domains\Players\Controllers\Api\RespectController::class, 'give'])
        ->whereNumber('user')
        ->middleware('throttle:20,1');
    // Забрать свой сегодняшний респект
    Route::delete('/players/{user}/respects', [\App\Domains\Players\Controllers\Api\RespectController::class, 'revoke'])
        ->whereNumber('user')
        ->middleware('throttle:20,1');
    Route::post('/players/{user}/visit', [\App\Domains\Players\Controllers\Api\RespectController::class, 'visit'])
        ->whereNumber('user')
        ->middleware('throttle:60,1');
    Route::get('/players/me/guests', [\App\Domains\Players\Controllers\Api\RespectController::class, 'guests']);

    Route::post('/players/{user}/polls/{block}', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'votePoll'])
        ->whereNumber('user')
        ->where('block', '[a-z0-9]{1,16}')
        ->middleware('throttle:30,1');

    // Погладить, покормить, поиграть — раз в день каждое
    Route::post('/players/{user}/pet/{action}', [\App\Domains\Players\Controllers\Api\PetController::class, 'interact'])
        ->whereNumber('user')
        ->whereIn('action', ['pet', 'feed', 'play'])
        ->middleware('throttle:30,1');
    Route::put('/players/me/pet-name', [\App\Domains\Players\Controllers\Api\PetController::class, 'rename'])
        ->middleware('throttle:10,1');

    Route::post('/players/{user}/guestbook', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'writeGuestbook'])
        ->whereNumber('user')
        ->middleware('throttle:6,1');
    Route::delete('/guestbook/{entry}', [\App\Domains\Players\Controllers\Api\ProfileBuilderController::class, 'deleteGuestbook'])
        ->whereNumber('entry');

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
        Route::post('/', [TierTestController::class, 'store'])->middleware('feature:tier_tests');
        Route::get('/{tierTest}', [TierTestController::class, 'show'])->whereNumber('tierTest');
        Route::post('/{tierTest}/cancel', [TierTestController::class, 'cancel'])->whereNumber('tierTest');
        Route::post('/{tierTest}/appeal', [\App\Domains\Tiers\Controllers\TierTestAppealController::class, 'store'])
            ->whereNumber('tierTest')
            ->middleware('throttle:5,1');
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
        Route::post('/', [ClanController::class, 'store'])->middleware('feature:clan_creation');

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
        // Лидер/офицер зовёт участников клана на войну (уведомления)
        Route::post('/{war}/call', [ClanWarController::class, 'call'])->whereNumber('war')->middleware('throttle:10,1');
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
    // Техподдержка: обращения игрока и переписка с персоналом
    Route::prefix('support')->group(function () {
        $support = \App\Domains\Support\Controllers\SupportController::class;
        Route::get('/tickets', [$support, 'index']);
        Route::post('/tickets', [$support, 'store'])->middleware('throttle:5,10');
        Route::get('/tickets/{ticket}', [$support, 'show'])->whereNumber('ticket');
        Route::post('/tickets/{ticket}/messages', [$support, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1');
        Route::post('/tickets/{ticket}/close', [$support, 'close'])->whereNumber('ticket');
    });

    Route::prefix('bridge')->group(function () {
        Route::get('/techniques', [BridgeController::class, 'index']);
        Route::post('/techniques', [BridgeController::class, 'store']);
        Route::delete('/submissions/{submission}', [BridgeController::class, 'destroy'])
            ->whereNumber('submission');

        // Игрок включает и выключает подвиды своей заявки
        Route::put('/submissions/{submission}/variants', [BridgeController::class, 'updateVariants'])
            ->whereNumber('submission');

        /*
         * Видео грузится частями: ролик на две минуты может весить сотни
         * мегабайт, одним запросом он не пройдёт.
         */
        Route::post('/uploads', [BridgeVideoController::class, 'init']);
        Route::post('/uploads/{upload:uuid}/chunks', [BridgeVideoController::class, 'chunk']);
        Route::post('/uploads/{upload:uuid}/complete', [BridgeVideoController::class, 'complete']);
    });

    /*
    |----------------------------------------------------------------------
    | БРИДЖ-ТЕСТЕР (role: bridge_tester, admin)
    |----------------------------------------------------------------------
    |
    | Раздел видят только эти роли, поэтому остальные игроки его не видят.
    */
    Route::middleware('role:bridge_tester,bridge_curator,admin')->prefix('bridge-review')->group(function () {
        Route::get('/submissions', [BridgeReviewController::class, 'index']);
        Route::post('/submissions/{submission}', [BridgeReviewController::class, 'review'])
            ->whereNumber('submission');

        /*
         * История всех проверок: куратор видит и то, что проводили
         * остальные тестеры, а не только свою очередь.
         */
        Route::get('/history', [BridgeReviewController::class, 'history']);

        // Звания бриджера: тестер выдаёт их вручную
        Route::get('/ranks', [BridgeReviewController::class, 'ranks']);
        Route::get('/players', [BridgeReviewController::class, 'players']);
        Route::post('/players/{user}/rank', [BridgeReviewController::class, 'assignRank'])
            ->whereNumber('user');
    });

    /*
    |----------------------------------------------------------------------
    | КУРАТОР БРИДЖ-ТЕСТА (role: bridge_curator, admin)
    |----------------------------------------------------------------------
    |
    | Куратор ведёт каталог: добавляет виды бриджа и подвиды к ним.
    */
    Route::middleware('role:bridge_curator,admin')->prefix('bridge-curator')->group(function () {
        Route::get('/techniques', [CuratorBridgeTechniqueController::class, 'index']);
        Route::post('/techniques', [CuratorBridgeTechniqueController::class, 'store']);
        Route::put('/techniques/{technique}', [CuratorBridgeTechniqueController::class, 'update'])
            ->whereNumber('technique');
        Route::delete('/techniques/{technique}', [CuratorBridgeTechniqueController::class, 'destroy'])
            ->whereNumber('technique');

        Route::post('/techniques/{technique}/variants', [CuratorBridgeTechniqueController::class, 'storeVariant'])
            ->whereNumber('technique');
        Route::put('/variants/{variant}', [CuratorBridgeTechniqueController::class, 'updateVariant'])
            ->whereNumber('variant');
        Route::delete('/variants/{variant}', [CuratorBridgeTechniqueController::class, 'destroyVariant'])
            ->whereNumber('variant');
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
    Route::get('/tier-test-appeals', [\App\Domains\Tiers\Controllers\TierTestAppealController::class, 'mine']);

    // Промокоды от администрации — в кошельке
    Route::post('/promo/redeem', [\App\Domains\Control\Controllers\ControlController::class, 'redeem'])->middleware('throttle:10,1');

    // Жалобы игроков
    Route::get('/reports/reasons', [\App\Domains\Moderation\Controllers\ReportController::class, 'reasons']);
    Route::post('/reports', [\App\Domains\Moderation\Controllers\ReportController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('role:moderator,admin')->prefix('admin')->group(function () {
        // Дашборд, счётчики меню, досье игрока
        Route::get('/dashboard', [\App\Domains\Moderation\Controllers\DashboardController::class, 'index']);
        Route::get('/counters', [\App\Domains\Moderation\Controllers\DashboardController::class, 'counters']);
        Route::get('/users/{user}/dossier', [\App\Domains\Moderation\Controllers\DashboardController::class, 'dossier'])->whereNumber('user');

        // Жалобы и журнал модерации
        Route::get('/reports', [\App\Domains\Moderation\Controllers\ReportController::class, 'index']);
        Route::post('/reports/{report}/resolve', [\App\Domains\Moderation\Controllers\ReportController::class, 'resolve'])->whereNumber('report');
        Route::get('/moderation-log', [\App\Domains\Moderation\Controllers\ReportController::class, 'log']);

        // Техподдержка: очередь обращений и ответы
        Route::get('/support', [\App\Domains\Support\Controllers\AdminSupportController::class, 'index']);
        Route::get('/support/{ticket}', [\App\Domains\Support\Controllers\AdminSupportController::class, 'show'])->whereNumber('ticket');
        Route::post('/support/{ticket}/messages', [\App\Domains\Support\Controllers\AdminSupportController::class, 'reply'])->whereNumber('ticket');
        Route::post('/support/{ticket}/close', [\App\Domains\Support\Controllers\AdminSupportController::class, 'close'])->whereNumber('ticket');
        // Поиск игрока для досье — модератору тоже (без почты в выдаче)
        Route::get('/players/search', [\App\Domains\Moderation\Controllers\ReportController::class, 'searchPlayers']);

        // Чистка профиля и заметки — модератору и админу
        Route::prefix('users/{user}')->whereNumber('user')->group(function () {
            $player = \App\Domains\Moderation\Controllers\PlayerAdminController::class;
            Route::post('/remove-avatar', [$player, 'removeAvatar']);
            Route::post('/remove-cover', [$player, 'removeCover']);
            Route::post('/clear-profile', [$player, 'clearProfile']);
            Route::post('/note', [$player, 'note']);
        });

        // Апелляции на результаты тир-тестов
        Route::get('/tier-test-appeals', [\App\Domains\Tiers\Controllers\TierTestAppealController::class, 'index']);
        Route::post('/tier-test-appeals/{appeal}/resolve', [\App\Domains\Tiers\Controllers\TierTestAppealController::class, 'resolve'])
            ->whereNumber('appeal');

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
        Route::delete('/tournaments/{tournament}/participants/{participant}', [AdminTournamentController::class, 'removeParticipant']);
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

        // Аккаунт игрока из досье: ник, пароль, почта, сессии, Minecraft
        Route::prefix('users/{user}')->whereNumber('user')->group(function () {
            $player = \App\Domains\Moderation\Controllers\PlayerAdminController::class;
            Route::post('/rename', [$player, 'rename']);
            Route::post('/send-password-reset', [$player, 'sendPasswordReset'])->middleware('throttle:5,1');
            Route::post('/temporary-password', [$player, 'temporaryPassword']);
            Route::post('/verify-email', [$player, 'verifyEmail']);
            Route::post('/logout-everywhere', [$player, 'logoutEverywhere']);
            Route::post('/unlink-minecraft', [$player, 'unlinkMinecraft']);
            Route::get('/bridge', [\App\Domains\Bridge\Controllers\Api\Admin\BridgeAdminController::class, 'show']);

            // Инвентарь игрока: посмотреть, надеть/снять, количество, забрать, питомец
            $inventory = \App\Domains\Shop\Controllers\Api\AdminInventoryController::class;
            Route::get('/inventory', [$inventory, 'index']);
            Route::post('/inventory/items/{shopItem}/equip', [$inventory, 'equip'])->whereNumber('shopItem');
            Route::post('/inventory/items/{shopItem}/unequip', [$inventory, 'unequip'])->whereNumber('shopItem');
            Route::put('/inventory/{row}', [$inventory, 'quantity'])->whereNumber('row');
            Route::delete('/inventory/{row}', [$inventory, 'destroy'])->whereNumber('row');
            Route::post('/inventory/{row}/reroll-pet', [$inventory, 'rerollPet'])->whereNumber('row');
        });

        // Бридж игрока: исправить оценки тестера, статус или удалить заявку
        Route::put('/bridge/submissions/{submission}', [\App\Domains\Bridge\Controllers\Api\Admin\BridgeAdminController::class, 'update'])->whereNumber('submission');
        Route::delete('/bridge/submissions/{submission}', [\App\Domains\Bridge\Controllers\Api\Admin\BridgeAdminController::class, 'destroy'])->whereNumber('submission');

        // Рефералы: кто кого привёл
        Route::get('/referrals', [\App\Domains\Moderation\Controllers\PlayerAdminController::class, 'referrals']);

        // «Пульт»: объявление, переключатели, событие xN, раздачи, промокоды
        // Чейнджлог и роадмап
        Route::prefix('project')->group(function () {
            $project = \App\Domains\Project\Controllers\AdminProjectController::class;
            Route::get('/changelog', [$project, 'changelog']);
            Route::post('/changelog', [$project, 'storeEntry']);
            Route::put('/changelog/{entry}', [$project, 'updateEntry'])->whereNumber('entry');
            Route::delete('/changelog/{entry}', [$project, 'destroyEntry'])->whereNumber('entry');
            Route::get('/roadmap', [$project, 'roadmap']);
            Route::post('/roadmap', [$project, 'storeItem']);
            Route::put('/roadmap/{item}', [$project, 'updateItem'])->whereNumber('item');
            Route::delete('/roadmap/{item}', [$project, 'destroyItem'])->whereNumber('item');
        });

        Route::prefix('control')->group(function () {
            $control = \App\Domains\Control\Controllers\ControlController::class;
            Route::get('/', [$control, 'show']);
            Route::put('/', [$control, 'update']);
            Route::post('/giveaway', [$control, 'giveaway']);
            Route::get('/online', [$control, 'online']);
            Route::get('/promo-codes', [$control, 'promoIndex']);
            Route::post('/promo-codes', [$control, 'promoStore']);
            Route::post('/promo-codes/{promo}/toggle', [$control, 'promoToggle'])->whereNumber('promo');
            Route::delete('/promo-codes/{promo}', [$control, 'promoDestroy'])->whereNumber('promo');
        });
        Route::post('/users/{user}/give-item', [\App\Domains\Control\Controllers\ControlController::class, 'giveItem'])->whereNumber('user');

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

    // Обзор: объявление, отметки, уровень, цели недели, лента
    Route::get('/overview', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'overview']);
    Route::get('/pulse', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'pulse']);
    Route::post('/checkin', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'checkIn'])->middleware('throttle:10,1');
    Route::put('/motd', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'updateMotd'])
        ->middleware(['clan.member:news', 'throttle:20,1']);

    // Казна: участники скидываются, лидер тратит на клановые предметы
    Route::get('/treasury', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'treasury']);
    Route::post('/treasury/donate', [\App\Domains\Clan\Controllers\Api\MyClanController::class, 'donate'])->middleware('throttle:20,1');

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
