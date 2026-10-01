<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantCoinsRequest;
use App\Http\Requests\Admin\StoreShopItemRequest;
use App\Http\Requests\Admin\UpdateShopRewardsRequest;
use App\Models\CoinTransaction;
use App\Models\ShopItem;
use App\Models\User;
use App\Services\CoinService;
use App\Services\RewardService;
use App\Services\ShopSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    /**
     * Каталог для админки (включая выключенные предметы).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShopItem::query()->orderBy('sort_order')->orderBy('price');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json([
            'items' => $query->get(),
            'types' => [
                ShopItem::TYPE_AVATAR_FRAME,
                ShopItem::TYPE_PROFILE_EFFECT,
                ShopItem::TYPE_ACCENT_COLOR,
                ShopItem::TYPE_CARD_BACKGROUND,
                ShopItem::TYPE_BADGE,
                ShopItem::TYPE_TIER_PRIORITY,
                ShopItem::TYPE_COIN_BUNDLE,
            ],
            'rarities' => ['common', 'rare', 'epic', 'legendary'],
        ]);
    }

    public function store(StoreShopItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $item = ShopItem::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(StoreShopItemRequest $request, ShopItem $shopItem): JsonResponse
    {
        $validated = $request->validated();

        $shopItem->update($validated);

        return response()->json(['item' => $shopItem->fresh()]);
    }

    public function destroy(ShopItem $shopItem): JsonResponse
    {
        $shopItem->delete();

        return response()->json(['ok' => true]);
    }

    public function toggle(ShopItem $shopItem): JsonResponse
    {
        $shopItem->update(['is_active' => ! $shopItem->is_active]);

        return response()->json(['item' => $shopItem->fresh()]);
    }

    /**
     * Настройки наград: таблица по тирам, бонусы, комиссия подарков, вкл/выкл источников.
     */
    public function rewards(): JsonResponse
    {
        return response()->json([
            'tier_table' => RewardService::tierTable(),
            'first_test_bonus' => ShopSettingService::getInt('tier_test.first_test_bonus', (int) config('apex.coins.tier_test.first_test_bonus')),
            'achievement_base' => ShopSettingService::getInt('achievement.base', (int) config('apex.coins.achievement.base')),
            'achievement_per_point' => ShopSettingService::getInt('achievement.per_point', (int) config('apex.coins.achievement.per_point')),
            'daily_bonus' => ShopSettingService::getInt('daily_bonus.amount', (int) config('apex.coins.daily_bonus.amount')),
            'daily_bonus_enabled' => ShopSettingService::getBool('daily_bonus.enabled', (bool) config('apex.coins.daily_bonus.enabled')),
            'gift_fee_percent' => ShopSettingService::getInt('gift.fee_percent', (int) config('apex.coins.gift.fee_percent')),
            'gift_daily_limit' => ShopSettingService::getInt('gift.daily_limit', (int) config('apex.coins.gift.daily_limit')),
            'sources' => ShopSettingService::get('sources', config('apex.coins.sources')),
            'stored' => ShopSettingService::all(),
        ]);
    }

    public function updateRewards(UpdateShopRewardsRequest $request): JsonResponse
    {
        // Админка присылает плоские ключи с точкой, сторонние клиенты —
        // вложенные массивы. В базу всегда пишем плоский вид: читатели
        // (ShopSettingService::get, RewardService) ищут именно такие ключи.
        foreach ($request->allFlat() as $key => $value) {
            ShopSettingService::put($key, $value);
        }

        return response()->json([
            'message' => 'Настройки экономики сохранены.',
            'stored' => ShopSettingService::all(),
        ]);
    }

    /**
     * Начислить или списать монеты вручную — «свои способы начисления».
     */
    public function grantCoins(GrantCoinsRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        try {
            CoinService::credit(
                $user,
                (int) $validated['amount'],
                CoinTransaction::SOURCE_ADMIN,
                $validated['reason'] ?? 'Ручное начисление администратором',
                null,
                ['actor_id' => $request->user()->id]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Баланс обновлён.',
            'balance' => (int) $user->fresh()->apex_coins,
        ]);
    }

    /**
     * Леджер: кто, сколько и за что получал.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = CoinTransaction::with(['user:id,username', 'actor:id,username'])->latest();

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Разворачивает вложенный вид настроек в плоские ключи с точкой.
     *
     * Из {'tier_test' => ['first_test_bonus' => 7]} получается
     * ['tier_test.first_test_bonus' => 7]. Значения-массивы (например
     * таблица per_tier) остаются массивом, но ключ становится плоским.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */

}
