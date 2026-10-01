<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopItem;
use App\Models\TierTest;
use App\Services\RewardService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Витрина магазина.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'items' => ShopService::catalog($user),
            'balance' => $user ? (int) $user->apex_coins : null,
            'tier_rewards' => RewardService::tierTable(),
            'currency' => config('apex.currency'),
        ]);
    }

    /**
     * Инвентарь: что куплено и что надето.
     */
    public function inventory(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'items' => ShopService::inventory($user),
            'equipped_badges' => ShopService::equippedBadges($user),
            'priority_charges' => ShopService::priorityCharges($user),
            'priority_candidates' => ShopService::priorityCandidates($user),
            'max_equipped_badges' => (int) config('apex.shop.max_equipped_badges', 3),
        ]);
    }

    public function show(ShopItem $shopItem): JsonResponse
    {
        abort_unless($shopItem->is_active, 404);

        return response()->json(['item' => ShopService::presentItem($shopItem)]);
    }

    /**
     * Купить предмет.
     */
    public function purchase(Request $request, ShopItem $shopItem): JsonResponse
    {
        $validated = $request->validate([
            'tier_test_id' => ['nullable', 'integer', 'exists:tier_tests,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $result = ShopService::purchase($request->user(), $shopItem, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "«{$shopItem->name}» куплено.",
            'balance' => $result['balance'],
            'item' => $result['item'],
            'tier_test' => $result['tier_test'],
            'inventory' => ShopService::inventory($request->user()->fresh()),
        ], 201);
    }

    /**
     * Надеть предмет.
     */
    public function equip(Request $request, ShopItem $shopItem): JsonResponse
    {
        try {
            $inventory = ShopService::equip($request->user(), $shopItem);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "«{$shopItem->name}» надето.",
            'inventory' => $inventory,
        ]);
    }

    /**
     * Снять предмет.
     */
    public function unequip(Request $request, ShopItem $shopItem): JsonResponse
    {
        try {
            $inventory = ShopService::unequip($request->user(), $shopItem);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "«{$shopItem->name}» снято.",
            'inventory' => $inventory,
        ]);
    }

    /**
     * Заявки, к которым можно применить приоритет, и число купленных зарядов.
     */
    public function priorityCandidates(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'tier_tests' => ShopService::priorityCandidates($user),
            'charges' => ShopService::priorityCharges($user),
        ]);
    }

    /**
     * Применить ранее купленный приоритет к конкретной заявке.
     */
    public function applyPriority(Request $request, TierTest $tierTest): JsonResponse
    {
        try {
            ShopService::applyPriority($request->user(), $tierTest);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $fresh = $tierTest->fresh();

        return response()->json([
            'message' => "Заявка #{$fresh->id} теперь в приоритете.",
            'tier_test' => [
                'id' => $fresh->id,
                'is_priority' => (bool) $fresh->is_priority,
                'priority_weight' => (int) $fresh->priority_weight,
            ],
            'charges' => ShopService::priorityCharges($request->user()),
            'inventory' => ShopService::inventory($request->user()),
        ]);
    }
}
