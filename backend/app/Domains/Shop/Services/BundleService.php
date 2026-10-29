<?php

namespace App\Domains\Shop\Services;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Наборы косметики, как в Discord: несколько предметов одной темы со
 * скидкой. Если часть набора уже есть, он дешевеет пропорционально
 * стоимости недостающего. В наборах бывают эксклюзивы (is_active = false),
 * которые по отдельности не продаются.
 */
class BundleService
{
    /** Предметы набора в порядке из metadata.items. */
    public static function items(ShopItem $bundle): Collection
    {
        $slugs = $bundle->metadata['items'] ?? [];

        $items = ShopItem::whereIn('slug', $slugs)->get()->keyBy('slug');

        // Питомцы — только из сундука, даже если остались в старом наборе
        return collect($slugs)->map(fn ($slug) => $items->get($slug))
            ->filter(fn (?ShopItem $item) => $item && ! $item->isCaseOnly())
            ->values();
    }

    /**
     * Цена набора для игрока: полная — если ничего нет, со скидкой на
     * уже купленное, 0 — если есть всё.
     *
     * @return array{full: int, value: int, missing_value: int, price: int, owned_all: bool}
     */
    public static function pricing(ShopItem $bundle, Collection $items, array $ownedIds): array
    {
        $value = (int) $items->sum('price');
        $missing = $items->reject(fn (ShopItem $item) => in_array($item->id, $ownedIds, true));
        $missingValue = (int) $missing->sum('price');

        $price = $value > 0 && $missingValue > 0
            ? (int) ceil($bundle->price * $missingValue / $value)
            : 0;

        return [
            'full' => (int) $bundle->price,
            'value' => $value,
            'missing_value' => $missingValue,
            'price' => $missing->isEmpty() ? 0 : max(1, $price),
            'owned_all' => $missing->isEmpty(),
        ];
    }

    /** Поля набора для витрины: состав, ценность и цена «для вас». */
    public static function present(ShopItem $bundle, array $ownedIds = [], bool $ownsEverything = false): array
    {
        $items = self::items($bundle);
        $pricing = self::pricing($bundle, $items, $ownsEverything ? $items->pluck('id')->all() : $ownedIds);

        return [
            'bundle' => [
                'items' => $items->map(fn (ShopItem $item) => ShopService::presentItem($item, [
                    'owned' => $ownsEverything || in_array($item->id, $ownedIds, true),
                    'exclusive' => ! $item->is_active,
                ]))->values(),
                'value' => $pricing['value'],
                'price_for_you' => $pricing['price'],
                'savings' => max(0, $pricing['value'] - $pricing['full']),
                'theme' => $bundle->metadata['theme'] ?? null,
                'mood' => $bundle->metadata['mood'] ?? null,
            ],
            'owned' => $pricing['owned_all'],
        ];
    }

    /**
     * Наборы для инвентаря: те, из которых у игрока есть хоть что-то
     * (админу — все). equipped_all — весь набор сейчас надет.
     */
    public static function forInventory(User $user): Collection
    {
        $ownsEverything = ShopService::ownsAllCosmetics($user);

        $owned = UserInventory::where('user_id', $user->id)->get()->keyBy('shop_item_id');

        return ShopItem::where('type', ShopItem::TYPE_BUNDLE)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(function (ShopItem $bundle) use ($owned, $ownsEverything) {
                $items = self::items($bundle)->filter(fn (ShopItem $item) => $item->isPersonalCosmetic());
                $have = $ownsEverything ? $items : $items->filter(fn (ShopItem $item) => $owned->has($item->id));

                if ($have->isEmpty()) {
                    return null;
                }

                $presented = ShopService::presentItem($bundle, self::present($bundle, $owned->keys()->map(fn ($id) => (int) $id)->all(), $ownsEverything));

                return [
                    ...$presented,
                    'owned_count' => $have->count(),
                    'total_count' => $items->count(),
                    // Надет, если надето всё, что надевает кнопка: по одному в слот и бейджи
                    'equipped_all' => self::targets($have)->every(fn (ShopItem $item) => (bool) $owned->get($item->id)?->equipped_at),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Надеть набор целиком: всё, что из него есть у игрока. В каждом слоте
     * (рамка, эффект, фон…) — первый предмет набора этого типа; бейджи —
     * пока есть место. Возвращает, сколько предметов надето.
     */
    public static function equip(User $user, ShopItem $bundle): int
    {
        if (! $bundle->isBundle()) {
            throw new RuntimeException('Это не набор.');
        }

        $ownsEverything = ShopService::ownsAllCosmetics($user);
        $ownedIds = UserInventory::where('user_id', $user->id)->pluck('shop_item_id')->map(fn ($id) => (int) $id)->all();

        $items = self::items($bundle)
            ->filter(fn (ShopItem $item) => $item->isPersonalCosmetic())
            ->filter(fn (ShopItem $item) => $ownsEverything || in_array($item->id, $ownedIds, true));

        if ($items->isEmpty()) {
            throw new RuntimeException('Из этого набора у вас пока ничего нет.');
        }

        $equipped = 0;

        foreach (self::targets($items) as $item) {
            try {
                ShopService::equip($user->fresh(), $item);
                $equipped++;
            } catch (RuntimeException) {
                // Бейдж уже надет или слоты бейджей заняты — пропускаем
            }
        }

        return $equipped;
    }

    /**
     * Что надевает кнопка «Надеть набор»: в каждом слоте первый предмет
     * этого типа (две рамки в наборе — вторая остаётся в инвентаре), бейджи — все.
     */
    private static function targets(Collection $items): Collection
    {
        $slots = [];

        return $items->filter(function (ShopItem $item) use (&$slots) {
            if ($item->type === ShopItem::TYPE_BADGE) {
                return true;
            }

            if (isset($slots[$item->type])) {
                return false;
            }

            return $slots[$item->type] = true;
        })->values();
    }

    public static function purchase(User $user, ShopItem $bundle): array
    {
        if (ShopService::ownsAllCosmetics($user)) {
            throw new RuntimeException('Вам вся косметика доступна бесплатно — просто наденьте её в инвентаре.');
        }

        $items = self::items($bundle);

        if ($items->isEmpty()) {
            throw new RuntimeException('Набор пока пуст.');
        }

        $ownedIds = UserInventory::where('user_id', $user->id)
            ->whereIn('shop_item_id', $items->pluck('id'))
            ->pluck('shop_item_id')
            ->all();

        $pricing = self::pricing($bundle, $items, $ownedIds);

        if ($pricing['owned_all']) {
            throw new RuntimeException('У вас уже есть всё из этого набора.');
        }

        if (! CoinService::canAfford($user, $pricing['price'])) {
            throw new RuntimeException('Недостаточно ApexCoin. Не хватает ' . ($pricing['price'] - (int) $user->apex_coins) . '.');
        }

        $granted = $items->reject(fn (ShopItem $item) => in_array($item->id, $ownedIds, true))->values();

        DB::transaction(function () use ($user, $bundle, $pricing, $granted) {
            CoinService::debit($user, $pricing['price'], CoinTransaction::SOURCE_PURCHASE, "Покупка: {$bundle->name}", [
                'reference_type' => ShopItem::class,
                'reference_id' => $bundle->id,
            ]);

            foreach ($granted as $item) {
                UserInventory::create([
                    'user_id' => $user->id,
                    'shop_item_id' => $item->id,
                    'quantity' => 1,
                    'acquired_at' => now(),
                ]);
            }
        });

        $user->refresh();

        return [
            'item' => ShopService::presentItem($bundle, self::present($bundle, $items->pluck('id')->all())),
            'granted' => $granted->map(fn (ShopItem $item) => ShopService::presentItem($item))->values(),
            'paid' => $pricing['price'],
            'balance' => (int) $user->apex_coins,
            'tier_test' => null,
            'clan_highlight' => null,
        ];
    }
}
