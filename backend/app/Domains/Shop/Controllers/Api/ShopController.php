<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Shop\Requests\Shop\PurchaseRequest;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Wallet\Services\RewardService;
use App\Domains\Shop\Services\BundleService;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Shop\Services\StickerService;
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
        $leaderClan = $user ? ShopService::leaderClan($user) : null;

        // Постранично (?limit=): витрина догружает предметы по курсору
        if ($request->filled('limit')) {
            $data = $request->validate([
                'limit' => ['integer', 'min:1', 'max:60'],
                'type' => ['nullable', 'string', 'max:40'],
                'cursor' => ['nullable', 'string', 'max:200'],
            ]);

            $page = ShopService::catalogPage($user, $data['type'] ?? null, $data['cursor'] ?? null, (int) $data['limit']);

            // Шапку витрины (наборы, вкладки, баланс) отдаём только с первой страницей
            if (! empty($data['cursor'])) {
                return response()->json($page);
            }

            return response()->json([
                ...$page,
                'type_counts' => ShopService::catalogTypeCounts($user),
                'bundles' => ShopService::catalog($user)->where('type', ShopItem::TYPE_BUNDLE)->values(),
                'balance' => $user ? (int) $user->apex_coins : null,
                'clan' => $leaderClan ? [
                    'id' => $leaderClan->id,
                    'tag' => $leaderClan->tag,
                    'treasury' => (int) $leaderClan->treasury,
                ] : null,
                'tier_rewards' => RewardService::tierTable(),
                'currency' => config('apex.currency'),
            ]);
        }

        return response()->json([
            'items' => ShopService::catalog($user),
            'balance' => $user ? (int) $user->apex_coins : null,
            // Лидеру — казна клана: клановые предметы можно оплатить из неё
            'clan' => $leaderClan ? [
                'id' => $leaderClan->id,
                'tag' => $leaderClan->tag,
                'treasury' => (int) $leaderClan->treasury,
            ] : null,
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

        // Постранично (?limit=): вкладка, поиск и сортировка на сервере
        if ($request->filled('limit')) {
            $data = $request->validate([
                'limit' => ['integer', 'min:1', 'max:60'],
                'tab' => ['nullable', 'string', 'max:40'],
                'q' => ['nullable', 'string', 'max:64'],
                'sort' => ['nullable', 'in:rarity,new,name'],
                'cursor' => ['nullable', 'string', 'max:200'],
            ]);

            $page = ShopService::inventoryPage(
                $user,
                $data['tab'] ?? 'all',
                $data['q'] ?? '',
                $data['sort'] ?? 'rarity',
                $data['cursor'] ?? null,
                (int) $data['limit'],
            );

            if (! empty($data['cursor'])) {
                return response()->json(['items' => $page['items'], 'next_cursor' => $page['next_cursor']]);
            }

            return response()->json([
                ...$page,
                'equipped_badges' => ShopService::equippedBadges($user),
                'priority_charges' => ShopService::priorityCharges($user),
                'priority_candidates' => ShopService::priorityCandidates($user),
                'max_equipped_badges' => (int) config('apex.shop.max_equipped_badges', 3),
                'bundles' => BundleService::forInventory($user),
            ]);
        }

        return response()->json([
            'items' => ShopService::inventory($user),
            'equipped_badges' => ShopService::equippedBadges($user),
            'priority_charges' => ShopService::priorityCharges($user),
            'priority_candidates' => ShopService::priorityCandidates($user),
            'max_equipped_badges' => (int) config('apex.shop.max_equipped_badges', 3),
            // Наборы, из которых что-то есть: надеваются одной кнопкой
            'bundles' => BundleService::forInventory($user),
        ]);
    }

    /** Надеть или снять конкретный экземпляр питомца (их бывает несколько). */
    public function togglePet(Request $request, \App\Domains\Shop\Models\UserInventory $row, string $action): JsonResponse
    {
        $user = $request->user();

        abort_unless($row->user_id === $user->id && $row->item?->type === ShopItem::TYPE_PROFILE_PET, 404);

        $action === 'equip'
            ? ShopService::equipPetRow($user, $row)
            : ShopService::unequipPetRow($user, $row);

        return response()->json([
            'message' => $action === 'equip' ? 'Питомец гуляет в профиле.' : 'Питомец ушёл в инвентарь.',
            'inventory' => ShopService::inventory($user->fresh())->all(),
        ]);
    }

    /** Стикерпаки игрока для выбора в чате. */
    public function stickers(Request $request, StickerService $stickers): JsonResponse
    {
        return response()->json(['packs' => $stickers->ownedPacks($request->user())]);
    }

    /** «Рандом»: надеть случайные предметы из своих во все слоты. */
    public function randomize(Request $request): JsonResponse
    {
        try {
            $count = ShopService::randomize($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Образ перемешан — надето предметов: {$count}.",
            'items' => ShopService::inventory($request->user()->fresh()),
        ]);
    }

    /** Надеть набор целиком. */
    public function equipBundle(Request $request, ShopItem $shopItem): JsonResponse
    {
        $user = $request->user();

        try {
            $count = BundleService::equip($user, $shopItem);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = $user->fresh();

        return response()->json([
            'message' => "{$shopItem->name} надет — предметов: {$count}.",
            'items' => ShopService::inventory($user),
            'equipped_badges' => ShopService::equippedBadges($user),
            'bundles' => BundleService::forInventory($user),
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
            // Подсветка клана: до какого момента действует
            'clan_highlight' => $result['clan_highlight'] ?? null,
            // Набор: что именно выдано и сколько списано с учётом скидки
            'granted' => $result['granted'] ?? null,
            'paid' => $result['paid'] ?? null,
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
