<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\ShopItem;
use App\Models\TierTest;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Магазин: каталог, покупка, надевание, приоритет тир-теста.
 */
class ShopService
{
    /**
     * Каталог для витрины. Если передан пользователь — помечает уже купленное.
     */
    public static function catalog(?User $user = null): Collection
    {
        $items = ShopItem::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        if (! $user) {
            return $items->map(fn (ShopItem $item) => self::presentItem($item));
        }

        $owned = UserInventory::where('user_id', $user->id)
            ->get()
            ->keyBy('shop_item_id');

        return $items->map(function (ShopItem $item) use ($owned, $user) {
            $row = $owned->get($item->id);

            return self::presentItem($item, [
                'owned' => $row !== null,
                'quantity' => $row?->quantity ?? 0,
                'equipped' => (bool) $row?->equipped_at,
                'can_afford' => (int) $user->apex_coins >= (int) $item->price,
            ]);
        });
    }

    public static function presentItem(ShopItem $item, array $extra = []): array
    {
        return array_merge([
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'description' => $item->description,
            'type' => $item->type,
            'rarity' => $item->rarity,
            'effect_value' => $item->effect_value,
            'price' => $item->price,
            'icon' => $item->icon,
            'color' => $item->color,
            'is_consumable' => $item->is_consumable,
            'is_repeatable' => $item->is_repeatable,
            'is_equippable' => $item->isEquippable(),
            'metadata' => $item->metadata,
            // Флаги покупки есть всегда: у гостя они по умолчанию ложные,
            // чтобы фронтенд не различал гостя и авторизованного по структуре ответа
            'owned' => false,
            'quantity' => 0,
            'equipped' => false,
            'can_afford' => false,
        ], $extra);
    }

    /**
     * Инвентарь пользователя.
     */
    public static function inventory(User $user): Collection
    {
        return UserInventory::with('item')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (UserInventory $row) => $row->item !== null)
            ->map(fn (UserInventory $row) => array_merge(
                self::presentItem($row->item),
                [
                    'inventory_id' => $row->id,
                    'quantity' => $row->quantity,
                    'equipped' => $row->isEquipped(),
                    'equipped_at' => $row->equipped_at?->toIso8601String(),
                    'acquired_at' => $row->acquired_at?->toIso8601String(),
                ]
            ))
            ->values();
    }

    /**
     * Выполнить операцию магазина, превратив бизнес-ошибку в 422.
     *
     * Раньше контроллер оборачивал каждую операцию в try/catch — теперь
     * это ответственность сервиса, а контроллер просто вызывает метод.
     */
    private static function toHttpError(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RuntimeException $e) {
            abort(response()->json(['message' => $e->getMessage()], 422));
        }
    }

    /**
     * Покупка с обработкой бизнес-ошибок.
     *
     * @param  array{tier_test_id?: int|null, quantity?: int}  $options
     * @return array{item: array, balance: int, tier_test: ?array}
     */
    public static function purchaseOrFail(User $user, ShopItem $item, array $options = []): array
    {
        return self::toHttpError(fn () => self::purchase($user, $item, $options));
    }

    public static function equipOrFail(User $user, ShopItem $item): mixed
    {
        return self::toHttpError(fn () => self::equip($user, $item));
    }

    public static function unequipOrFail(User $user, ShopItem $item): mixed
    {
        return self::toHttpError(fn () => self::unequip($user, $item));
    }

    public static function applyPriorityOrFail(User $user, TierTest $tierTest): mixed
    {
        return self::toHttpError(fn () => self::applyPriority($user, $tierTest));
    }

    /**
     * Покупка предмета.
     *
     * @param  array{tier_test_id?: int|null, quantity?: int}  $options
     * @return array{item: array, balance: int, tier_test: ?array}
     */
    public static function purchase(User $user, ShopItem $item, array $options = []): array
    {
        if (! $item->is_active) {
            throw new RuntimeException('Этот предмет больше не продаётся.');
        }

        $quantity = max(1, (int) ($options['quantity'] ?? 1));

        if ($quantity > 1 && ! $item->isStackable()) {
            throw new RuntimeException('Этот предмет можно купить только один раз.');
        }

        $owned = UserInventory::where('user_id', $user->id)
            ->where('shop_item_id', $item->id)
            ->first();

        if ($owned && ! $item->isStackable()) {
            throw new RuntimeException('Этот предмет уже есть в вашем инвентаре.');
        }

        if ($owned && $item->max_quantity !== null && $owned->quantity + $quantity > $item->max_quantity) {
            throw new RuntimeException('Достигнут максимум по этому предмету.');
        }

        $price = $item->price * $quantity;

        if (! CoinService::canAfford($user, $price)) {
            throw new RuntimeException('Недостаточно ApexCoin. Не хватает ' . ($price - (int) $user->apex_coins) . '.');
        }

        $tierTest = null;

        // Приоритет: заявку можно указать сразу, но это не обязательно —
        // заряд кладётся в инвентарь и применяется позже (страница «Инвентарь»).
        if ($item->type === ShopItem::TYPE_TIER_PRIORITY && ! empty($options['tier_test_id'])) {
            $tierTest = self::resolvePriorityTarget($user, (int) $options['tier_test_id']);

            if (! $tierTest) {
                throw new RuntimeException('Заявка не найдена или уже недоступна.');
            }

            if ($tierTest->is_priority) {
                throw new RuntimeException('У этой заявки уже есть приоритет.');
            }
        }

        DB::transaction(function () use ($user, $item, $quantity, $price, $options, &$tierTest) {
            CoinService::debit(
                $user,
                $price,
                CoinTransaction::SOURCE_PURCHASE,
                "Покупка: {$item->name}" . ($quantity > 1 ? " ×{$quantity}" : ''),
                [
                    'reference_type' => ShopItem::class,
                    'reference_id' => $item->id,
                    'meta' => array_filter([
                        'slug' => $item->slug,
                        'quantity' => $quantity,
                        'tier_test_id' => $tierTest?->id,
                    ], fn ($v) => $v !== null),
                ]
            );

            $inventory = UserInventory::firstOrNew([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
            ]);

            // Сколько применений даёт одна покупка (у пачки ×5 — пять)
            $charges = (int) ($item->metadata['charges'] ?? 1);
            $inventory->quantity = ($inventory->quantity ?? 0) + ($charges * $quantity);
            $inventory->acquired_at = $inventory->acquired_at ?? now();
            $inventory->save();

            // Набор монет дополнительно зачисляем на баланс.
            // Запись в инвентаре всё равно нужна: без неё не работали
            // проверки «уже куплено» и max_quantity, и набор с
            // is_repeatable = false можно было покупать бесконечно.
            if ($item->type === ShopItem::TYPE_COIN_BUNDLE) {
                $amount = (int) ($item->metadata['amount'] ?? 0) * $quantity;

                if ($amount > 0) {
                    CoinService::credit(
                        $user,
                        $amount,
                        CoinTransaction::SOURCE_OTHER,
                        "Активация набора: {$item->name}",
                        null,
                        ['reference_type' => ShopItem::class, 'reference_id' => $item->id]
                    );
                }
            }

            if ($tierTest) {
                self::applyPriorityCharge($user, $item, $tierTest);
            }
        });

        $user->refresh();

        return [
            'item' => self::presentItem($item),
            'balance' => (int) $user->apex_coins,
            'tier_test' => $tierTest ? [
                'id' => $tierTest->id,
                'is_priority' => true,
                'priority_purchased_at' => $tierTest->priority_purchased_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Найти заявку, к которой применяем приоритет: указанную или самую старую свободную.
     */
    public static function resolvePriorityTarget(User $user, ?int $tierTestId): ?TierTest
    {
        $query = TierTest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress']);

        if ($tierTestId) {
            return (clone $query)->whereKey($tierTestId)->first();
        }

        return $query->orderBy('created_at')->first();
    }

    /**
     * Применить ранее купленный заряд приоритета к заявке.
     */
    public static function applyPriority(User $user, TierTest $tierTest): int
    {
        if ($tierTest->user_id !== $user->id) {
            throw new RuntimeException('Это не ваша заявка.');
        }

        if (! in_array($tierTest->status, ['pending', 'in_progress'], true)) {
            throw new RuntimeException('Приоритет можно применить только к активной заявке.');
        }

        if ($tierTest->is_priority) {
            throw new RuntimeException('У этой заявки уже есть приоритет.');
        }

        $item = self::priorityChargeItem($user);

        if (! $item) {
            throw new RuntimeException('Нет купленного приоритета. Купи его в магазине.');
        }

        return DB::transaction(fn () => self::applyPriorityCharge($user, $item, $tierTest));
    }

    /**
     * Сколько зарядов приоритета осталось у игрока.
     */
    public static function priorityCharges(User $user): int
    {
        return (int) UserInventory::where('user_id', $user->id)
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_TIER_PRIORITY))
            ->sum('quantity');
    }

    /**
     * Списать один заряд и пометить заявку приоритетной.
     * Вызывается только внутри транзакции.
     */
    private static function applyPriorityCharge(User $user, ShopItem $item, TierTest $tierTest): int
    {
        $inventory = UserInventory::where('user_id', $user->id)
            ->where('shop_item_id', $item->id)
            ->first();

        if (! $inventory || $inventory->quantity < 1) {
            throw new RuntimeException('Приоритет закончился — купи новый в магазине.');
        }

        $tierTest->update([
            'is_priority' => true,
            'priority_purchased_at' => now(),
            'priority_price_paid' => $item->price,
            'priority_weight' => (int) config('apex.priority.default_weight', 100) + (int) $tierTest->priority_weight,
        ]);

        // Расходник сгорает на применении; пустые записи не держим в инвентаре
        if ($inventory->quantity <= 1) {
            $inventory->delete();
        } else {
            $inventory->decrement('quantity');
        }

        return $tierTest->priority_weight;
    }

    /**
     * Предмет-приоритет, заряды которого есть у игрока.
     */
    private static function priorityChargeItem(User $user): ?ShopItem
    {
        $row = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->where('quantity', '>', 0)
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_TIER_PRIORITY))
            ->orderBy('id')
            ->first();

        return $row?->item;
    }

    /**
     * Заявки пользователя, доступные для покупки приоритета — для выпадающего списка.
     */
    public static function priorityCandidates(User $user): Collection
    {
        return TierTest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at')
            ->get(['id', 'mode', 'status', 'is_priority', 'created_at'])
            ->map(fn (TierTest $test) => [
                'id' => $test->id,
                'mode' => $test->mode,
                'status' => $test->status,
                'is_priority' => (bool) $test->is_priority,
                'created_at' => $test->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Надеть предметы. Одиночные слоты (рамка, эффект, акцент) заменяют друг друга,
     * бейджи надеваются поверх до лимита из конфига.
     */
    public static function equip(User $user, ShopItem $item): array
    {
        if (! $item->isEquippable()) {
            throw new RuntimeException('Этот предмет нельзя надеть.');
        }

        $row = UserInventory::where('user_id', $user->id)
            ->where('shop_item_id', $item->id)
            ->first();

        if (! $row) {
            throw new RuntimeException('Сначала нужно купить этот предмет.');
        }

        DB::transaction(function () use ($user, $item, $row) {
            if ($item->type === ShopItem::TYPE_BADGE) {
                self::equipBadge($user, $row);
            } else {
                self::equipSingleSlot($user, $item, $row);
            }
        });

        return self::inventory($user->fresh())->all();
    }

    private static function equipSingleSlot(User $user, ShopItem $item, UserInventory $row): void
    {
        $field = self::profileFieldFor($item->type);

        if (! $field) {
            throw new RuntimeException('Неизвестный тип косметики.');
        }

        $value = $item->effect_value ?: $item->slug;

        // Снимаем предыдущий предмет того же слота
        UserInventory::where('user_id', $user->id)
            ->where('equipped_at', '!=', null)
            ->whereHas('item', fn ($q) => $q->where('type', $item->type))
            ->update(['equipped_at' => null]);

        $user->{$field} = $value;
        $user->save();

        $row->equipped_at = now();
        $row->save();
    }

    private static function equipBadge(User $user, UserInventory $row): void
    {
        $limit = (int) config('apex.shop.max_equipped_badges', 3);

        $equipped = UserInventory::where('user_id', $user->id)
            ->where('equipped_at', '!=', null)
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_BADGE))
            ->count();

        if ($equipped >= $limit) {
            throw new RuntimeException("Можно надеть не больше {$limit} бейджей.");
        }

        $row->equipped_at = now();
        $row->save();

        self::syncBadgeField($user);
    }

    public static function unequip(User $user, ShopItem $item): array
    {
        $row = UserInventory::where('user_id', $user->id)
            ->where('shop_item_id', $item->id)
            ->first();

        if (! $row || ! $row->isEquipped()) {
            throw new RuntimeException('Этот предмет не надет.');
        }

        DB::transaction(function () use ($user, $item, $row) {
            $row->equipped_at = null;
            $row->save();

            if ($item->type === ShopItem::TYPE_BADGE) {
                self::syncBadgeField($user);
            } else {
                $field = self::profileFieldFor($item->type);

                if ($field) {
                    $user->{$field} = $item->type === ShopItem::TYPE_AVATAR_FRAME
                        ? 'default'
                        : null;
                    $user->save();
                }
            }
        });

        return self::inventory($user->fresh())->all();
    }

    /**
     * Поле профиля, в которое пишется надетый предмет.
     */
    public static function profileFieldFor(string $type): ?string
    {
        return match ($type) {
            ShopItem::TYPE_AVATAR_FRAME => 'avatar_frame',
            ShopItem::TYPE_PROFILE_EFFECT => 'profile_effect',
            ShopItem::TYPE_ACCENT_COLOR => 'accent_color',
            ShopItem::TYPE_CARD_BACKGROUND => 'card_background',
            default => null,
        };
    }

    /**
     * Пересобрать список надетых бейджей в профиле.
     */
    private static function syncBadgeField(User $user): void
    {
        $badges = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->where('equipped_at', '!=', null)
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_BADGE))
            ->get()
            ->map(fn (UserInventory $row) => [
                'slug' => $row->item->slug,
                'name' => $row->item->name,
                'icon' => $row->item->icon,
                'color' => $row->item->color,
            ])
            ->values()
            ->all();

        $user->equipped_badges = $badges;
        $user->save();
    }

    /**
     * Надетые бейджи пользователя (для профиля).
     */
    public static function equippedBadges(User $user): array
    {
        return $user->equipped_badges ?? [];
    }
}
