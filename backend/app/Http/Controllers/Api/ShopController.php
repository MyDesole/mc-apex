<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\PurchaseRequest;
use App\Models\ShopItem;
use App\Models\TierTest;
use App\Services\RewardService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Магазин. Контроллер тонкий: логика покупок — в ShopService.
 *
 * Ошибки бизнес-правил (не хватает монет, лимит бейджей) сервис
 * превращает в ответ 422 — контроллер о них не знает.
 */
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

    public function purchase(PurchaseRequest $request, ShopItem $shopItem): JsonResponse
    {
        $result = ShopService::purchaseOrFail($request->user(), $shopItem, $request->validated());

        return response()->json([
            'message' => "«{$shopItem->name}» куплено.",
            'balance' => $result['balance'],
            'item' => $result['item'],
            'tier_test' => $result['tier_test'],
            'inventory' => ShopService::inventory($request->user()->fresh()),
        ], 201);
    }

    public function equip(Request $request, ShopItem $shopItem): JsonResponse
    {
        $inventory = ShopService::equipOrFail($request->user(), $shopItem);

        return response()->json([
            'message' => "«{$shopItem->name}» надето.",
            'inventory' => $inventory,
        ]);
    }

    public function unequip(Request $request, ShopItem $shopItem): JsonResponse
    {
        $inventory = ShopService::unequipOrFail($request->user(), $shopItem);

        return response()->json([
            'message' => "«{$shopItem->name}» снято.",
            'inventory' => $inventory,
        ]);
    }

    /**
     * Заявки, к которым можно применить приоритет, и число зарядов.
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
     * Применить купленный приоритет к конкретной заявке.
     */
    public function applyPriority(Request $request, TierTest $tierTest): JsonResponse
    {
        ShopService::applyPriorityOrFail($request->user(), $tierTest);

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

    /**
     * Выполнить операцию магазина, превратив бизнес-ошибку в 422.
     * Один обработчик вместо try/catch в каждом методе.
     */
}
