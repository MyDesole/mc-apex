<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateItem($request);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $item = ShopItem::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, ShopItem $shopItem): JsonResponse
    {
        $validated = $this->validateItem($request, $shopItem);

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

    public function updateRewards(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier_test.per_tier' => ['sometimes', 'array'],
            'tier_test.per_tier.*' => ['integer', 'min:0', 'max:1000000'],
            'tier_test.first_test_bonus' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'achievement.base' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'achievement.per_point' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'daily_bonus.amount' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'daily_bonus.enabled' => ['sometimes', 'boolean'],
            'gift.fee_percent' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'gift.daily_limit' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'sources' => ['sometimes', 'array'],
            'sources.*' => ['boolean'],
        ]);

        foreach ($validated as $key => $value) {
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
    public function grantCoins(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

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

    private function validateItem(Request $request, ?ShopItem $item = null): array
    {
        return $request->validate([
            'name' => [$item ? 'sometimes' : 'required', 'string', 'max:128'],
            'slug' => ['nullable', 'string', 'max:64', 'unique:shop_items,slug' . ($item ? ',' . $item->id : '')],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => [$item ? 'sometimes' : 'required', 'in:avatar_frame,profile_effect,accent_color,card_background,badge,tier_priority,coin_bundle'],
            'rarity' => ['nullable', 'in:common,rare,epic,legendary'],
            'effect_value' => ['nullable', 'string', 'max:128'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_consumable' => ['nullable', 'boolean'],
            'is_repeatable' => ['nullable', 'boolean'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
