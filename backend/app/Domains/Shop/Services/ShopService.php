<?php

namespace App\Domains\Shop\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanInventory;
use App\Domains\Clan\Services\ClanTreasuryService;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Clan\Support\ClanHighlight;
use App\Domains\Clan\Support\ClanLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Domains\Wallet\Services\CoinService;

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

        // Клановые предметы покупает только лидер — остальным их не показываем
        $leaderClan = $user ? self::leaderClan($user) : null;

        $items = $items
            ->reject(fn (ShopItem $item) => $item->isClanItem() && ! $leaderClan)
            ->values();

        if (! $user) {
            return $items->map(fn (ShopItem $item) => self::presentItem(
                $item,
                $item->isBundle() ? BundleService::present($item) : [],
            ));
        }

        $owned = UserInventory::where('user_id', $user->id)
            ->get()
            ->keyBy('shop_item_id');

        $clanOwned = $leaderClan
            ? ClanInventory::where('clan_id', $leaderClan->id)->get()->keyBy('shop_item_id')
            : collect();

        $ownsEverything = self::ownsAllCosmetics($user);
        $ownedIds = $owned->keys()->map(fn ($id) => (int) $id)->all();

        return $items->map(function (ShopItem $item) use ($owned, $clanOwned, $user, $leaderClan, $ownsEverything, $ownedIds) {
            // Набор: состав, ценность и цена с учётом уже купленного
            if ($item->isBundle()) {
                $bundle = BundleService::present($item, $ownedIds, $ownsEverything);

                return self::presentItem($item, [
                    ...$bundle,
                    'can_afford' => (int) $user->apex_coins >= $bundle['bundle']['price_for_you'],
                ]);
            }

            // Админу вся личная косметика доступна без покупки
            if ($ownsEverything && $item->isPersonalCosmetic()) {
                return self::presentItem($item, [
                    'owned' => true,
                    'quantity' => 1,
                    'equipped' => (bool) $owned->get($item->id)?->equipped_at,
                    'can_afford' => true,
                ]);
            }

            $row = $item->type === ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE
                ? $clanOwned->get($item->id)
                : $owned->get($item->id);

            // Из казны клан платит со скидкой за уровень
            $treasuryPrice = $item->isClanItem() && $leaderClan
                ? ClanLevel::discounted((int) $item->price, (int) ($leaderClan->level ?: 1))
                : null;

            $canAfford = (int) $user->apex_coins >= (int) $item->price
                || ($treasuryPrice !== null && (int) $leaderClan->treasury >= $treasuryPrice);

            return self::presentItem($item, array_filter([
                'owned' => $row !== null,
                'quantity' => $row?->quantity ?? ($row ? 1 : 0),
                'equipped' => (bool) $row?->equipped_at,
                'can_afford' => $canAfford,
                'treasury_price' => $treasuryPrice,
            ], fn ($value) => $value !== null));
        });
    }

    /** Слоты профиля, которые перемешивает кнопка «Рандом». */
    private const RANDOM_SLOTS = [
        ShopItem::TYPE_AVATAR_FRAME,
        ShopItem::TYPE_PROFILE_EFFECT,
        ShopItem::TYPE_ACCENT_COLOR,
        ShopItem::TYPE_SKIN_BACKDROP,
        ShopItem::TYPE_CARD_THEME,
        ShopItem::TYPE_PROFILE_LAYOUT,
        ShopItem::TYPE_NAME_EFFECT,
        ShopItem::TYPE_PROFILE_ENTRANCE,
        ShopItem::TYPE_CURSOR_TRAIL,
    ];

    /**
     * «Рандом»: в каждом слоте надеть случайный предмет из своих и
     * случайные бейджи до лимита. Возвращает, сколько предметов надето.
     */
    public static function randomize(User $user): int
    {
        $items = self::ownsAllCosmetics($user)
            ? ShopItem::whereIn('type', [...self::RANDOM_SLOTS, ShopItem::TYPE_BADGE])->get()
            : ShopItem::whereIn('id', UserInventory::where('user_id', $user->id)->pluck('shop_item_id'))->get();

        $items = $items->filter(fn (ShopItem $item) => $item->isPersonalCosmetic());

        if ($items->isEmpty()) {
            throw new RuntimeException('Пока нечего перемешивать — загляните в магазин или откройте кейс.');
        }

        $count = 0;

        foreach (self::RANDOM_SLOTS as $type) {
            $pick = $items->where('type', $type)->shuffle()->first();

            if ($pick) {
                self::equip($user->fresh(), $pick);
                $count++;
            }
        }

        // Бейджи: снимаем все и надеваем случайные до лимита
        $badges = $items->where('type', ShopItem::TYPE_BADGE)->shuffle()
            ->take((int) config('apex.shop.max_equipped_badges', 3));

        if ($badges->isNotEmpty()) {
            UserInventory::where('user_id', $user->id)
                ->whereNotNull('equipped_at')
                ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_BADGE))
                ->update(['equipped_at' => null]);

            foreach ($badges as $badge) {
                self::equip($user->fresh(), $badge);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Админам вся личная косметика доступна сразу, без покупки:
     * в инвентаре видна целиком, надевается без записи о покупке.
     */
    public static function ownsAllCosmetics(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    /** Клан, которым руководит игрок, или null. */
    public static function leaderClan(User $user): ?Clan
    {
        $clan = $user->clanMember?->clan;

        return $clan && $clan->isLeader($user->id) ? $clan : null;
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
        $rows = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (UserInventory $row) => $row->item !== null);

        $inventory = $rows
            ->map(fn (UserInventory $row) => array_merge(
                self::presentItem($row->item),
                [
                    'inventory_id' => $row->id,
                    'shop_item_id' => $row->shop_item_id,
                    'quantity' => $row->quantity,
                    'equipped' => $row->isEquipped(),
                    'equipped_at' => $row->equipped_at?->toIso8601String(),
                    'acquired_at' => $row->acquired_at?->toIso8601String(),
                ]
            ))
            ->values();

        if (! self::ownsAllCosmetics($user)) {
            return $inventory;
        }

        // Админ: добавляем всю личную косметику, которой ещё нет в инвентаре
        $have = $rows->pluck('shop_item_id')->all();

        $free = ShopItem::query()
            ->whereIn('type', ShopItem::EQUIPPABLE)
            ->whereNotIn('id', $have)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ShopItem $item) => $item->isPersonalCosmetic())
            ->map(fn (ShopItem $item) => array_merge(self::presentItem($item), [
                'inventory_id' => "free-{$item->id}",
                'shop_item_id' => $item->id,
                'quantity' => 1,
                'owned' => true,
                'equipped' => false,
                'equipped_at' => null,
                'acquired_at' => null,
            ]));

        return $inventory->concat($free)->values();
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

        if ($item->isBundle()) {
            return BundleService::purchase($user, $item);
        }

        if (self::ownsAllCosmetics($user) && $item->isPersonalCosmetic()) {
            throw new RuntimeException('Вам вся косметика доступна бесплатно — просто наденьте её в инвентаре.');
        }

        $quantity = max(1, (int) ($options['quantity'] ?? 1));

        if ($quantity > 1 && ! $item->isStackable()) {
            throw new RuntimeException('Этот предмет можно купить только один раз.');
        }

        // Подсветка клана и её оформление: покупает лидер для клана.
        // Права проверяем до всего остального, чтобы игрок сразу видел причину.
        $clan = null;
        $isStyle = $item->type === ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE;
        $fromTreasury = $item->isClanItem() && ($options['pay_from'] ?? null) === 'treasury';

        if ($item->isClanItem()) {
            $clan = $user->clanMember?->clan;

            if (! $clan) {
                throw new RuntimeException('Клановые предметы доступны только лидеру клана. Вы не состоите в клане.');
            }

            if (! $clan->isLeader($user->id)) {
                throw new RuntimeException('Недостаточно прав: клановые предметы покупает только лидер клана. Попросите лидера или скиньтесь в казну клана.');
            }

            if ($isStyle && ! $clan->isHighlightActive()) {
                throw new RuntimeException('Сначала купите подсветку клана, потом её оформление.');
            }
        }

        // Оформление лежит в инвентаре клана, остальное — в личном
        $owned = $isStyle
            ? ClanInventory::where('clan_id', $clan->id)->where('shop_item_id', $item->id)->first()
            : UserInventory::where('user_id', $user->id)->where('shop_item_id', $item->id)->first();

        if ($owned && ! $item->isStackable()) {
            throw new RuntimeException($isStyle
                ? 'Это оформление уже есть в инвентаре клана — наденьте его в настройках клана.'
                : 'Этот предмет уже есть в вашем инвентаре.');
        }

        if ($owned && ! $isStyle && $item->max_quantity !== null && $owned->quantity + $quantity > $item->max_quantity) {
            throw new RuntimeException('Достигнут максимум по этому предмету.');
        }

        $price = $item->price * $quantity;

        if ($fromTreasury) {
            // Уровень клана даёт скидку на покупки из казны
            $price = ClanLevel::discounted($price, (int) ($clan->level ?: 1));

            if ((int) $clan->treasury < $price) {
                throw new RuntimeException('В казне клана недостаточно ApexCoin. Не хватает ' . ($price - (int) $clan->treasury) . '.');
            }
        } elseif (! CoinService::canAfford($user, $price)) {
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

        DB::transaction(function () use ($user, $item, $quantity, $price, $options, &$tierTest, &$clan, $isStyle, $fromTreasury) {
            $title = "Покупка: {$item->name}" . ($quantity > 1 ? " ×{$quantity}" : '');

            if ($fromTreasury) {
                app(ClanTreasuryService::class)->spend($clan, $user, $price, $title);
            } else {
                CoinService::debit(
                    $user,
                    $price,
                    CoinTransaction::SOURCE_PURCHASE,
                    $title,
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
            }

            // Оформление подсветки — в инвентарь клана и сразу надеваем
            if ($clan && $isStyle) {
                self::applyHighlightStyle($clan, $item);
                self::markClanStyleEquipped($clan, $item, $user);

                return;
            }

            // Срок подсветки — расходник клана, в личный инвентарь не кладём
            if ($clan) {
                self::extendClanHighlight($clan, $item, $quantity);

                return;
            }

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
            'clan_highlight' => $clan ? [
                'clan_id' => $clan->id,
                'is_highlighted' => (bool) $clan->is_highlighted,
                'highlight_until' => $clan->highlight_until?->toIso8601String(),
                'treasury' => (int) $clan->fresh()->treasury,
            ] : null,
        ];
    }

    /**
     * Применяет купленное оформление подсветки: цвет или эффект.
     *
     * Что именно покупают, определяется metadata.kind, а значение
     * лежит в effect_value предмета.
     */
    private static function applyHighlightStyle(Clan $clan, ShopItem $item): void
    {
        $kind = $item->metadata['kind'] ?? null;
        $value = $item->effect_value;

        if ($kind === 'color') {
            if (! ClanHighlight::isValidColor($value)) {
                throw new RuntimeException('Неизвестный цвет подсветки.');
            }

            $clan->update(['highlight_color' => $value]);

            return;
        }

        if ($kind === 'effect') {
            if (! ClanHighlight::isValidEffect($value)) {
                throw new RuntimeException('Неизвестный эффект подсветки.');
            }

            $clan->update(['highlight_effect' => $value]);

            return;
        }

        throw new RuntimeException('У этого предмета не задано, что он меняет.');
    }

    /**
     * Включает подсветку клана и продлевает её при повторной покупке.
     *
     * Срок берётся из metadata предмета (days, по умолчанию 30).
     * Если подсветка ещё активна — добавляем дни к текущему сроку,
     * иначе считаем от сегодняшнего дня.
     */
    private static function extendClanHighlight(Clan $clan, ShopItem $item, int $quantity = 1): void
    {
        $days = max(1, (int) ($item->metadata['days'] ?? 30)) * max(1, $quantity);

        $from = $clan->highlight_until && $clan->highlight_until->isFuture()
            ? $clan->highlight_until
            : now();

        $clan->update([
            'is_highlighted' => true,
            'highlight_until' => $from->copy()->addDays($days),
        ]);
    }

    /**
     * Снимает подсветку у кланов, у которых срок истёк.
     *
     * @return int сколько кланов обновлено
     */
    public static function expireClanHighlights(): int
    {
        return Clan::where('is_highlighted', true)
            ->whereNotNull('highlight_until')
            ->where('highlight_until', '<=', now())
            ->update([
                'is_highlighted' => false,
                'highlight_until' => null,
            ]);
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

        // Оформление подсветки лежит в инвентаре клана
        if ($item->type === ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE) {
            DB::transaction(fn () => self::equipClanHighlightStyle($user, $item));

            return self::inventory($user->fresh())->all();
        }

        $row = UserInventory::where('user_id', $user->id)
            ->where('shop_item_id', $item->id)
            ->first();

        // Админу запись в инвентаре создаётся при первом надевании
        if (! $row && self::ownsAllCosmetics($user) && $item->isPersonalCosmetic()) {
            $row = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'quantity' => 1,
                'acquired_at' => now(),
            ]);
        }

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

    /**
     * Применить купленное оформление подсветки к клану лидера.
     *
     * Один цвет и один эффект одновременно: надевая новый цвет,
     * снимаем предыдущий, эффект при этом не трогаем.
     */
    private static function equipClanHighlightStyle(User $user, ShopItem $item): void
    {
        $field = self::clanHighlightField($item->metadata['kind'] ?? null);

        if (! $field) {
            throw new RuntimeException('У этого предмета не задано, что он меняет.');
        }

        $clan = self::clanForHighlight($user);

        if (! ClanInventory::where('clan_id', $clan->id)->where('shop_item_id', $item->id)->exists()) {
            throw new RuntimeException('Этого оформления нет в инвентаре клана.');
        }

        $clan->update([$field => $item->effect_value]);

        self::markClanStyleEquipped($clan, $item, $user);
    }

    /**
     * Отметить оформление надетым в инвентаре клана (создав запись при покупке).
     * Одновременно надеты один цвет и один эффект: прежний того же вида снимаем.
     */
    private static function markClanStyleEquipped(Clan $clan, ShopItem $item, User $buyer): void
    {
        $kind = $item->metadata['kind'] ?? null;

        ClanInventory::where('clan_id', $clan->id)
            ->whereNotNull('equipped_at')
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE)->where('metadata->kind', $kind))
            ->update(['equipped_at' => null]);

        $row = ClanInventory::firstOrNew(['clan_id' => $clan->id, 'shop_item_id' => $item->id]);
        $row->purchased_by ??= $buyer->id;
        $row->equipped_at = now();
        $row->save();
    }

    /**
     * Поле клана, в которое пишется оформление, по виду предмета.
     */
    private static function clanHighlightField(?string $kind): ?string
    {
        return match ($kind) {
            'color' => 'highlight_color',
            'effect' => 'highlight_effect',
            default => null,
        };
    }

    /**
     * Клан текущего игрока с проверкой права на оформление.
     */
    private static function clanForHighlight(User $user): Clan
    {
        $clan = $user->clanMember?->clan;

        if (! $clan) {
            throw new RuntimeException('Подсветка доступна только участникам клана.');
        }

        if (! $clan->isLeader($user->id)) {
            throw new RuntimeException('Оформление подсветки может менять только лидер.');
        }

        if (! $clan->isHighlightActive()) {
            throw new RuntimeException('Сначала купите подсветку клана, потом её оформление.');
        }

        return $clan;
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
        $isStyle = $item->type === ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE;
        $clan = $isStyle ? self::clanForHighlight($user) : null;

        $row = $isStyle
            ? ClanInventory::where('clan_id', $clan->id)->where('shop_item_id', $item->id)->first()
            : UserInventory::where('user_id', $user->id)->where('shop_item_id', $item->id)->first();

        if (! $row || ! $row->isEquipped()) {
            throw new RuntimeException('Этот предмет не надет.');
        }

        DB::transaction(function () use ($user, $item, $row, $clan) {
            $row->equipped_at = null;
            $row->save();

            if ($clan) {
                // Возвращаем оформление по умолчанию, а не пустое значение
                $field = self::clanHighlightField($item->metadata['kind'] ?? null);

                if ($field) {
                    $clan->update([
                        $field => $field === 'highlight_color'
                            ? ClanHighlight::DEFAULT_COLOR
                            : ClanHighlight::DEFAULT_EFFECT,
                    ]);
                }
            } elseif ($item->type === ShopItem::TYPE_BADGE) {
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
     * Купленное оформление подсветки клана, раздельно по видам.
     *
     * Нужно, чтобы лидер мог применить уже купленное, не заходя
     * в инвентарь: возвращаем цвета и эффекты с пометкой «надето».
     *
     * @return array{colors: array<int, array>, effects: array<int, array>}
     */
    public static function clanHighlightStyles(User $user): array
    {
        $clanId = $user->clanMember?->clan_id;

        $rows = $clanId
            ? ClanInventory::with('item')->where('clan_id', $clanId)->get()
            : collect();

        $colors = [];
        $effects = [];

        foreach ($rows as $row) {
            $item = $row->item;

            if (! $item) {
                continue;
            }

            $entry = [
                'id' => $item->id,
                'name' => $item->name,
                'value' => $item->effect_value,
                'kind' => $item->metadata['kind'] ?? null,
                'equipped' => $row->isEquipped(),
            ];

            if ($entry['kind'] === 'color') {
                $colors[] = $entry;
            } elseif ($entry['kind'] === 'effect') {
                $effects[] = $entry;
            }
        }

        return ['colors' => $colors, 'effects' => $effects];
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
            ShopItem::TYPE_SKIN_BACKDROP => 'skin_backdrop',
            ShopItem::TYPE_CARD_THEME => 'card_theme',
            ShopItem::TYPE_PROFILE_LAYOUT => 'profile_layout',
            ShopItem::TYPE_NAME_EFFECT => 'name_effect',
            ShopItem::TYPE_PROFILE_ENTRANCE => 'profile_entrance',
            ShopItem::TYPE_CURSOR_TRAIL => 'cursor_trail',
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
                // Пиксельная картинка и анимация бейджа (новые бейджи)
                'sprite' => $row->item->metadata['sprite'] ?? null,
                'anim' => $row->item->metadata['anim'] ?? null,
                // Премиальное оформление чипа: holo, neon, glitch, galaxy, fire, gold
                'style' => $row->item->metadata['style'] ?? null,
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
