<?php

/*
|--------------------------------------------------------------------------
| Блок роутов магазина и ApexCoin
|--------------------------------------------------------------------------
| Вставить в backend/routes/api.php:
|
|  1. Импорты (см. блок «use») — рядом с остальными use в начале файла.
|  2. Публичные роуты — рядом с другими публичными (например, после строк
|     Route::get('/players/{user}', [PlayerController::class, 'show']) ...).
|  3. Авторизованные роуты — внутрь группы Route::middleware('auth:sanctum')->group(...).
|  4. Админские роуты — внутрь групп с middleware('role:moderator,admin') и ('role:admin').
*/

// ======================== 1. ИМПОРТЫ ========================
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GiftController;
use App\Http\Controllers\Api\Admin\ShopController as AdminShopController;

// ======================== 2. ПУБЛИЧНЫЕ ========================
// Витрина магазина доступна всем; для гостя флаг owned всегда false.
Route::get('/shop', [ShopController::class, 'index']);
Route::get('/shop/{shopItem}', [ShopController::class, 'show'])->whereNumber('shopItem');

// ======================== 3. АВТОРИЗОВАННЫЕ ========================
/*
    Вставить внутрь Route::middleware('auth:sanctum')->group(function () { ... });
*/

// --- Кошелёк ApexCoin ---
Route::prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'index']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
    Route::post('/daily-bonus', [WalletController::class, 'claimDaily']);
});

// --- Магазин: покупка и надевание ---
Route::prefix('shop')->group(function () {
    Route::get('/inventory', [ShopController::class, 'inventory']);
    Route::get('/priority-candidates', [ShopController::class, 'priorityCandidates']);
    Route::post('/{shopItem}/purchase', [ShopController::class, 'purchase'])->whereNumber('shopItem');
    Route::post('/{shopItem}/equip', [ShopController::class, 'equip'])->whereNumber('shopItem');
    Route::post('/{shopItem}/unequip', [ShopController::class, 'unequip'])->whereNumber('shopItem');
});

// --- Подарки валюты друзьям ---
Route::prefix('gifts')->group(function () {
    Route::get('/limits', [GiftController::class, 'limits']);
    Route::post('/{user}', [GiftController::class, 'store'])->whereNumber('user');
});

// ======================== 4. АДМИНКА ========================
/*
    Блок управления каталогом — в группу Route::middleware('role:moderator,admin')->prefix('admin')
    Блок начисления монет и леджера — в группу Route::middleware('role:admin')->prefix('admin')
*/

// --- Каталог (модератор + админ) ---
Route::get('/shop-items', [AdminShopController::class, 'index']);
Route::post('/shop-items', [AdminShopController::class, 'store']);
Route::put('/shop-items/{shopItem}', [AdminShopController::class, 'update'])->whereNumber('shopItem');
Route::delete('/shop-items/{shopItem}', [AdminShopController::class, 'destroy'])->whereNumber('shopItem');
Route::post('/shop-items/{shopItem}/toggle', [AdminShopController::class, 'toggle'])->whereNumber('shopItem');
Route::get('/shop-rewards', [AdminShopController::class, 'rewards']);
Route::put('/shop-rewards', [AdminShopController::class, 'updateRewards']);

// --- Начисления и леджер (только админ) ---
Route::post('/users/{user}/coins', [AdminShopController::class, 'grantCoins'])->whereNumber('user');
Route::get('/coin-transactions', [AdminShopController::class, 'transactions']);
