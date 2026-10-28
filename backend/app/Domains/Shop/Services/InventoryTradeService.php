<?php

namespace App\Domains\Shop\Services;

use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Продать предмет магазину или подарить другу.
 *
 * Продажа: купленное — за половину цены покупки, выпавшее из сундуков и
 * подаренное — как повтор из кейса (15%), иначе легендарки из сундука за
 * 750 продавались бы за тысячи. Подарок: только другу; у получателя он
 * считается подаренным, так что монеты через подарки не отмыть.
 */
class InventoryTradeService
{
    public const SELL_SHARE = 0.5;

    /** Что не продаётся и не дарится: расходники и монеты. */
    private const LOCKED_TYPES = [ShopItem::TYPE_COIN_BUNDLE, ShopItem::TYPE_TIER_PRIORITY];

    public static function tradeable(UserInventory $row): bool
    {
        $item = $row->item;

        return $item
            && ! $item->is_consumable
            && ! in_array($item->type, self::LOCKED_TYPES, true)
            && (int) $item->price > 0;
    }

    /** Сколько магазин даст за одну штуку. */
    public static function sellValue(UserInventory $row): int
    {
        if (! self::tradeable($row)) {
            return 0;
        }

        $item = $row->item;
        $paid = $row->paid_price ?? self::legacyPaid($row);

        return $paid
            ? max(1, (int) floor($paid * self::SELL_SHARE))
            : max(1, (int) floor($item->price * LootCases::DUPLICATE_SHARE));
    }

    /** Старые записи без цены: куплено, если есть покупка и это не подарок. */
    private static function legacyPaid(UserInventory $row): ?int
    {
        if ($row->gifted_by) {
            return null;
        }

        // Один запрос на игрока за весь список инвентаря, а не на каждый предмет
        static $bought = [];
        $bought[$row->user_id] ??= CoinTransaction::where('user_id', $row->user_id)
            ->where('source', CoinTransaction::SOURCE_PURCHASE)
            ->where('reference_type', ShopItem::class)
            ->pluck('reference_id')
            ->flip()
            ->all();

        return isset($bought[$row->user_id][$row->shop_item_id]) ? (int) $row->item->price : null;
    }

    public function sell(User $user, UserInventory $row): array
    {
        $this->assertOwn($user, $row);
        $value = self::sellValue($row);
        $item = $row->item;

        DB::transaction(function () use ($user, $row, $item, $value) {
            $this->takeOff($user, $row);

            if ($row->quantity > 1) {
                $row->decrement('quantity');
            } else {
                $row->delete();
            }

            CoinService::credit($user, $value, CoinTransaction::SOURCE_OTHER, "Продажа: {$item->name}", null, [
                'reference_type' => ShopItem::class,
                'reference_id' => $item->id,
            ]);
        });

        return ['coins' => $value, 'balance' => (int) $user->fresh()->apex_coins];
    }

    public function gift(User $user, UserInventory $row, User $friend): array
    {
        $this->assertOwn($user, $row);
        $item = $row->item;

        if ($friend->id === $user->id) {
            throw new RuntimeException('Себе подарить нельзя.');
        }

        if (! $user->isFriendsWith($friend->id)) {
            throw new RuntimeException('Дарить можно только друзьям.');
        }

        $isPet = $item->type === ShopItem::TYPE_PROFILE_PET;
        $theirs = UserInventory::where('user_id', $friend->id)->where('shop_item_id', $item->id)->first();

        if ($theirs && ! $isPet && ! $item->isStackable()) {
            throw new RuntimeException("У {$friend->username} уже есть «{$item->name}».");
        }

        DB::transaction(function () use ($user, $row, $item, $friend, $isPet, $theirs) {
            $this->takeOff($user, $row);

            // Питомец — тот же экземпляр (номер и черты) переезжает к другу
            if ($isPet || ($row->quantity <= 1 && ! $theirs)) {
                $row->forceFill([
                    'user_id' => $friend->id,
                    'equipped_at' => null,
                    'gifted_by' => $user->id,
                    'paid_price' => null,
                    'acquired_at' => now(),
                ])->save();

                return;
            }

            $row->quantity > 1 ? $row->decrement('quantity') : $row->delete();

            $target = $theirs ?? new UserInventory(['user_id' => $friend->id, 'shop_item_id' => $item->id, 'quantity' => 0]);
            $target->quantity = (int) $target->quantity + 1;
            $target->gifted_by = $user->id;
            $target->acquired_at ??= now();
            $target->save();
        });

        $friend->notify(new ModerationNotification(
            'item_gift',
            "Тебе подарок от {$user->username}: «{$item->name}». Загляни в инвентарь!",
            ['from_id' => $user->id, 'item_id' => $item->id, 'link' => '/inventory'],
        ));

        return ['ok' => true];
    }

    private function assertOwn(User $user, UserInventory $row): void
    {
        if ($row->user_id !== $user->id) {
            throw new RuntimeException('Это не твой предмет.');
        }

        if (! self::tradeable($row)) {
            throw new RuntimeException('Этот предмет нельзя продать или подарить.');
        }

        if ($busy = \App\Domains\Players\Support\PetLocks::reason($row->id)) {
            throw new RuntimeException($busy);
        }
    }

    /** Надетое — сначала снять, чтобы профиль не ссылался на чужое. */
    private function takeOff(User $user, UserInventory $row): void
    {
        if (! $row->isEquipped()) {
            return;
        }

        if ($row->item->type === ShopItem::TYPE_PROFILE_PET) {
            ShopService::unequipPetRow($user, $row);

            return;
        }

        ShopService::unequip($user, $row->item);
        $row->refresh();
    }
}
