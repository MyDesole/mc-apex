<?php

namespace App\Domains\Shop\Services;

use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Аукцион питомцев: ставки с таймером.
 *
 * Лот — конкретный экземпляр питомца. Ставка списывает монеты сразу
 * (заморозка), прошлому лидеру они возвращаются. Ставка в последние
 * минуты продлевает торги — без «снайпинга». Закрытие — по расписанию
 * и лениво при любом обращении к аукциону.
 */
class PetAuctionService
{
    public const DURATIONS = [12, 24, 48];
    public const MIN_START = 10;
    // Шаг ставки: 5% от текущей, но не меньше 10
    public const STEP_SHARE = 0.05;
    public const MIN_STEP = 10;
    // Ставка в последние 2 минуты продлевает торги на 2 минуты
    public const SNIPE_SECONDS = 120;

    public static function onAuction(int $inventoryId): bool
    {
        return DB::table('pet_auctions')->where('inventory_id', $inventoryId)->where('status', 'active')->exists();
    }

    public function list(?User $viewer): array
    {
        $this->closeExpired();

        $rows = DB::table('pet_auctions')
            ->where('status', 'active')
            ->orderBy('ends_at')
            ->limit(60)
            ->get();

        return $rows->map(fn ($a) => $this->present($a, $viewer))->all();
    }

    public function show(int $id, ?User $viewer): array
    {
        $this->closeExpired();

        $auction = DB::table('pet_auctions')->find($id);
        abort_unless($auction, 404, 'Лот не найден.');

        return $this->present($auction, $viewer) + [
            'bids' => DB::table('pet_auction_bids as b')
                ->join('users as u', 'u.id', '=', 'b.user_id')
                ->where('b.auction_id', $id)
                ->orderByDesc('b.id')
                ->limit(20)
                ->get(['u.id', 'u.username', 'b.amount', 'b.created_at'])
                ->map(fn ($b) => [
                    'user' => ['id' => $b->id, 'username' => $b->username],
                    'amount' => (int) $b->amount,
                    'created_at' => Carbon::parse($b->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    public function create(User $seller, int $inventoryId, int $startPrice, int $hours): array
    {
        if (! in_array($hours, self::DURATIONS, true)) {
            throw new RuntimeException('Срок торгов: 12, 24 или 48 часов.');
        }

        if ($startPrice < self::MIN_START) {
            throw new RuntimeException('Стартовая цена — от ' . self::MIN_START . ' ApexCoin.');
        }

        $row = UserInventory::with('item')->find($inventoryId);

        if (! $row || $row->user_id !== $seller->id) {
            throw new RuntimeException('Это не твой питомец.');
        }

        if ($row->item?->type !== ShopItem::TYPE_PROFILE_PET) {
            throw new RuntimeException('На аукцион выставляются только питомцы.');
        }

        if (self::onAuction($row->id)) {
            throw new RuntimeException('Этот питомец уже на аукционе.');
        }

        $id = DB::transaction(function () use ($seller, $row, $startPrice, $hours) {
            // На аукционе питомец не гуляет по профилю
            if ($row->isEquipped()) {
                ShopService::unequipPetRow($seller, $row);
            }

            return DB::table('pet_auctions')->insertGetId([
                'seller_id' => $seller->id,
                'inventory_id' => $row->id,
                'start_price' => $startPrice,
                'ends_at' => now()->addHours($hours),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $this->show($id, $seller);
    }

    public function bid(User $bidder, int $id, int $amount): array
    {
        $this->closeExpired();

        DB::transaction(function () use ($bidder, $id, $amount) {
            $auction = DB::table('pet_auctions')->lockForUpdate()->find($id);

            if (! $auction || $auction->status !== 'active' || Carbon::parse($auction->ends_at)->isPast()) {
                throw new RuntimeException('Торги по этому лоту закончились.');
            }

            if ($auction->seller_id === $bidder->id) {
                throw new RuntimeException('Нельзя ставить на своего питомца.');
            }

            if ($auction->top_bidder_id === $bidder->id) {
                throw new RuntimeException('Твоя ставка и так лидирует.');
            }

            $min = self::minBid($auction);

            if ($amount < $min) {
                throw new RuntimeException("Ставка — от {$min} ApexCoin.");
            }

            if (! CoinService::canAfford($bidder->fresh(), $amount)) {
                throw new RuntimeException('Недостаточно ApexCoin для такой ставки.');
            }

            // Замораживаем ставку, прошлому лидеру — возврат
            CoinService::debit($bidder, $amount, CoinTransaction::SOURCE_OTHER, "Ставка на аукционе питомцев, лот #{$id}");

            if ($auction->top_bidder_id) {
                $previous = User::find($auction->top_bidder_id);

                if ($previous) {
                    CoinService::credit($previous, (int) $auction->current_price, CoinTransaction::SOURCE_OTHER, "Возврат ставки: лот #{$id} перебит");
                    $previous->notify(new ModerationNotification(
                        'auction_outbid',
                        "Твою ставку на лот #{$id} перебили — монеты вернулись. Можно поставить больше.",
                        ['auction_id' => $id, 'link' => "/auction?lot={$id}"],
                    ));
                }
            }

            $endsAt = Carbon::parse($auction->ends_at);
            if (now()->diffInSeconds($endsAt, false) < self::SNIPE_SECONDS) {
                $endsAt = now()->addSeconds(self::SNIPE_SECONDS);
            }

            DB::table('pet_auctions')->where('id', $id)->update([
                'current_price' => $amount,
                'top_bidder_id' => $bidder->id,
                'bids_count' => DB::raw('bids_count + 1'),
                'ends_at' => $endsAt,
                'updated_at' => now(),
            ]);

            DB::table('pet_auction_bids')->insert([
                'auction_id' => $id,
                'user_id' => $bidder->id,
                'amount' => $amount,
                'created_at' => now(),
            ]);
        });

        return $this->show($id, $bidder);
    }

    public function cancel(User $seller, int $id): void
    {
        $auction = DB::table('pet_auctions')->find($id);

        if (! $auction || $auction->seller_id !== $seller->id || $auction->status !== 'active') {
            throw new RuntimeException('Лот не найден.');
        }

        if ($auction->bids_count > 0) {
            throw new RuntimeException('Ставки уже есть — снять лот нельзя.');
        }

        DB::table('pet_auctions')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /** Закрыть закончившиеся торги: питомец — победителю, монеты — продавцу. */
    public function closeExpired(): int
    {
        $ended = DB::table('pet_auctions')
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->pluck('id');

        foreach ($ended as $id) {
            DB::transaction(function () use ($id) {
                $auction = DB::table('pet_auctions')->lockForUpdate()->find($id);

                if (! $auction || $auction->status !== 'active') {
                    return;
                }

                $row = UserInventory::with('item')->find($auction->inventory_id);
                $seller = User::find($auction->seller_id);
                $winner = $auction->top_bidder_id ? User::find($auction->top_bidder_id) : null;

                if (! $winner || ! $row) {
                    DB::table('pet_auctions')->where('id', $id)->update(['status' => 'expired', 'updated_at' => now()]);
                    // Ставка была, а питомца нет (удалён) — вернуть монеты
                    if ($winner && ! $row) {
                        CoinService::credit($winner, (int) $auction->current_price, CoinTransaction::SOURCE_OTHER, "Возврат: лот #{$id} снят");
                    }
                    $seller?->notify(new ModerationNotification('auction_expired', "Торги по лоту #{$id} закончились без ставок — питомец остался у тебя.", ['link' => '/inventory']));

                    return;
                }

                $price = (int) $auction->current_price;

                $row->forceFill([
                    'user_id' => $winner->id,
                    'equipped_at' => null,
                    'gifted_by' => null,
                    // Куплен на торгах — продаётся магазину от цены покупки
                    'paid_price' => $price,
                    'acquired_at' => now(),
                ])->save();

                if ($seller) {
                    ShopService::syncPets($seller);
                    CoinService::credit($seller, $price, CoinTransaction::SOURCE_OTHER, "Продажа на аукционе: лот #{$id}");
                    $seller->notify(new ModerationNotification('auction_sold', "Твой питомец продан на аукционе за {$price} ApexCoin!", ['link' => '/auction']));
                }

                $winner->notify(new ModerationNotification('auction_won', "Победа в торгах по лоту #{$id}! Питомец уже в инвентаре.", ['link' => '/inventory']));

                DB::table('pet_auctions')->where('id', $id)->update(['status' => 'sold', 'updated_at' => now()]);
            });
        }

        return $ended->count();
    }

    public static function minBid(object $auction): int
    {
        if (! $auction->current_price) {
            return (int) $auction->start_price;
        }

        $step = max(self::MIN_STEP, (int) ceil($auction->current_price * self::STEP_SHARE));

        return (int) $auction->current_price + $step;
    }

    private function present(object $a, ?User $viewer): array
    {
        $row = UserInventory::with('item')->find($a->inventory_id);
        $seller = User::find($a->seller_id, ['id', 'username', 'avatar']);
        $leader = $a->top_bidder_id ? User::find($a->top_bidder_id, ['id', 'username']) : null;

        return [
            'id' => $a->id,
            'status' => $a->status,
            'pet' => $row?->item ? [
                ...ShopService::presentItem($row->item),
                'pet' => [...(PetTraits::present($row->pet_traits, $row->pet_serial) ?? []), 'name' => $row->pet_name],
            ] : null,
            'seller' => $seller ? ['id' => $seller->id, 'username' => $seller->username, 'avatar_url' => $seller->avatar_url] : null,
            'leader' => $leader ? ['id' => $leader->id, 'username' => $leader->username] : null,
            'start_price' => (int) $a->start_price,
            'current_price' => $a->current_price ? (int) $a->current_price : null,
            'min_bid' => self::minBid($a),
            'bids_count' => (int) $a->bids_count,
            'ends_at' => Carbon::parse($a->ends_at)->toIso8601String(),
            'mine' => $viewer && $viewer->id === $a->seller_id,
            'leading' => $viewer && $viewer->id === $a->top_bidder_id,
        ];
    }
}
