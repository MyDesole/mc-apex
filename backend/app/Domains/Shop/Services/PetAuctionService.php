<?php

namespace App\Domains\Shop\Services;

use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Events\AuctionChanged;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use App\Support\Broadcasting\SafeBroadcast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Аукцион питомцев: ставки с таймером и чат у каждого лота.
 *
 * Лот — конкретный экземпляр питомца, с его чертами и уровнем. Ставка
 * списывает монеты сразу (заморозка), прошлому лидеру они возвращаются.
 * Ставка в последние минуты продлевает торги — без «снайпинга». Закрытие —
 * по расписанию и лениво при любом обращении к аукциону. Все изменения
 * сразу уходят зрителям через Reverb.
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
    // Одновременно на торгах — не больше трёх питомцев от игрока
    public const MAX_ACTIVE = 3;
    // Снять лот со ставками — неустойка 10% от текущей ставки
    public const PENALTY_SHARE = 0.10;

    // Чат: длина сообщения и пауза между сообщениями одного игрока
    public const MESSAGE_MAX = 300;
    public const MESSAGE_GAP_SECONDS = 3;

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

        return $this->presentMany($rows, $viewer);
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

        if ($busy = \App\Domains\Players\Support\PetLocks::reason($row->id)) {
            throw new RuntimeException($busy);
        }

        if (self::activeCount($seller->id) >= self::MAX_ACTIVE) {
            throw new RuntimeException('На торгах может быть не больше ' . self::MAX_ACTIVE . ' твоих питомцев — дождись конца торгов или сними лот.');
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

        $this->broadcastLot($id);

        return $this->show($id, $seller);
    }

    public static function activeCount(int $sellerId): int
    {
        return DB::table('pet_auctions')->where('seller_id', $sellerId)->where('status', 'active')->count();
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

        $this->broadcastLot($id);

        return $this->show($id, $bidder);
    }

    /**
     * Забрать питомца с торгов в любой момент. Без ставок — бесплатно;
     * со ставками лидеру возвращаются монеты, а продавец платит неустойку.
     *
     * @return int неустойка
     */
    public function cancel(User $seller, int $id): int
    {
        $this->closeExpired();

        $penalty = DB::transaction(function () use ($seller, $id) {
            $auction = DB::table('pet_auctions')->lockForUpdate()->find($id);

            if (! $auction || $auction->seller_id !== $seller->id || $auction->status !== 'active') {
                throw new RuntimeException('Лот не найден или торги уже закончились.');
            }

            $penalty = self::penaltyFor($auction);

            if ($penalty > 0) {
                if (! CoinService::canAfford($seller->fresh(), $penalty)) {
                    throw new RuntimeException("Чтобы снять лот со ставками, нужна неустойка {$penalty} ApexCoin.");
                }

                CoinService::debit($seller, $penalty, CoinTransaction::SOURCE_OTHER, "Неустойка: лот #{$id} снят с торгов");
            }

            if ($auction->top_bidder_id && $leader = User::find($auction->top_bidder_id)) {
                CoinService::credit($leader, (int) $auction->current_price, CoinTransaction::SOURCE_OTHER, "Возврат ставки: лот #{$id} снят продавцом");
                $leader->notify(new ModerationNotification(
                    'auction_cancelled',
                    "Продавец снял лот #{$id} с торгов — твоя ставка вернулась.",
                    ['auction_id' => $id, 'link' => '/auction'],
                ));
            }

            DB::table('pet_auctions')->where('id', $id)->update([
                'status' => 'cancelled',
                'penalty' => $penalty ?: null,
                'updated_at' => now(),
            ]);

            return $penalty;
        });

        $this->broadcastLot($id);

        return $penalty;
    }

    public static function penaltyFor(object $auction): int
    {
        return $auction->current_price ? max(1, (int) ceil($auction->current_price * self::PENALTY_SHARE)) : 0;
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

                // Строка инвентаря та же — питомец уходит со своим уровнем и чертами
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

            $this->broadcastLot($id);
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

    // ---- Чат лота ----

    public function messages(int $id): array
    {
        abort_unless(DB::table('pet_auctions')->where('id', $id)->exists(), 404, 'Лот не найден.');

        $auction = DB::table('pet_auctions')->find($id);

        return DB::table('pet_auction_messages')
            ->where('auction_id', $id)
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->reverse()
            ->map(fn ($m) => $this->presentMessage($m, $auction))
            ->values()
            ->all();
    }

    public function postMessage(User $author, int $id, string $body): array
    {
        $auction = DB::table('pet_auctions')->find($id);
        abort_unless($auction, 404, 'Лот не найден.');

        // Без разметки и лишних пробелов: только текст
        $body = trim(preg_replace('/\s+/u', ' ', strip_tags($body)));
        $body = mb_substr($body, 0, self::MESSAGE_MAX);

        if ($body === '') {
            throw new RuntimeException('Пустое сообщение.');
        }

        if ($auction->status !== 'active') {
            throw new RuntimeException('Торги закончились — чат лота закрыт.');
        }

        $last = DB::table('pet_auction_messages')
            ->where('auction_id', $id)
            ->where('user_id', $author->id)
            ->orderByDesc('id')
            ->first();

        if ($last && Carbon::parse($last->created_at)->diffInSeconds(now(), true) < self::MESSAGE_GAP_SECONDS) {
            throw new RuntimeException('Не так быстро — подожди пару секунд.');
        }

        if ($last && mb_strtolower($last->body) === mb_strtolower($body) && Carbon::parse($last->created_at)->diffInSeconds(now(), true) < 60) {
            throw new RuntimeException('Это сообщение ты уже отправил.');
        }

        $messageId = DB::table('pet_auction_messages')->insertGetId([
            'auction_id' => $id,
            'user_id' => $author->id,
            'body' => $body,
            'created_at' => now(),
        ]);

        $message = $this->presentMessage(DB::table('pet_auction_messages')->find($messageId), $auction);

        SafeBroadcast::send(new AuctionChanged($id, 'message', ['message' => $message]));

        return $message;
    }

    /** Удалить сообщение: модератор — любое, автор — своё. */
    public function deleteMessage(User $user, int $id, int $messageId): void
    {
        $message = DB::table('pet_auction_messages')->where('auction_id', $id)->find($messageId);

        if (! $message || ($message->user_id !== $user->id && ! $user->isModerator())) {
            throw new RuntimeException('Сообщение не найдено.');
        }

        DB::table('pet_auction_messages')->where('id', $messageId)->delete();

        SafeBroadcast::send(new AuctionChanged($id, 'message_deleted', ['id' => $messageId]));
    }

    private function presentMessage(object $m, object $auction): array
    {
        $author = User::find($m->user_id, ['id', 'username', 'avatar', 'role']);

        return [
            'id' => $m->id,
            'body' => $m->body,
            'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
            'user' => $author ? [
                'id' => $author->id,
                'username' => $author->username,
                'avatar_url' => $author->avatar_url,
            ] : null,
            'is_seller' => $m->user_id === $auction->seller_id,
            'is_leader' => $m->user_id === $auction->top_bidder_id,
        ];
    }

    // ---- Представление лота ----

    private function broadcastLot(int $id): void
    {
        $auction = DB::table('pet_auctions')->find($id);

        if ($auction) {
            SafeBroadcast::send(new AuctionChanged($id, 'lot', ['lot' => $this->present($auction, null)]));
        }
    }

    private function present(object $a, ?User $viewer): array
    {
        return $this->presentMany(collect([$a]), $viewer)[0];
    }

    /**
     * Лоты пачкой: питомцы, продавцы, лидеры, уровни и счётчики чата —
     * по одному запросу на всё, а не на каждый лот.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $lots
     */
    private function presentMany(\Illuminate\Support\Collection $lots, ?User $viewer): array
    {
        if ($lots->isEmpty()) {
            return [];
        }

        $inventoryIds = $lots->pluck('inventory_id')->all();
        $pets = UserInventory::with('item')->whereIn('id', $inventoryIds)->get()->keyBy('id');
        $users = User::whereIn('id', $lots->pluck('seller_id')->merge($lots->pluck('top_bidder_id'))->filter()->unique()->all())
            ->get(['id', 'username', 'avatar'])->keyBy('id');
        $levels = PetService::levels($inventoryIds);
        $messages = DB::table('pet_auction_messages')->whereIn('auction_id', $lots->pluck('id')->all())
            ->groupBy('auction_id')->selectRaw('auction_id, count(*) as total')->pluck('total', 'auction_id');

        return $lots->map(fn ($a) => $this->presentLot(
            $a,
            $viewer,
            $pets->get($a->inventory_id),
            $users->get($a->seller_id),
            $a->top_bidder_id ? $users->get($a->top_bidder_id) : null,
            $levels[$a->inventory_id] ?? 1,
            (int) ($messages[$a->id] ?? 0),
        ))->values()->all();
    }

    private function presentLot(object $a, ?User $viewer, ?UserInventory $row, ?User $seller, ?User $leader, int $level, int $messages): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'pet' => $row?->item ? [
                ...ShopService::presentItem($row->item),
                'pet' => [
                    ...(PetTraits::present($row->pet_traits, $row->pet_serial) ?? []),
                    'name' => $row->pet_name,
                    'level' => $level,
                ],
            ] : null,
            'level' => $level,
            // Рейтинг арены уходит с питомцем — покупатель видит, насколько он боевой
            'arena' => $row ? [
                'rating' => (int) $row->pet_rating,
                'wins' => (int) $row->pet_wins,
                'losses' => (int) $row->pet_losses,
            ] : null,
            'seller' => $seller ? ['id' => $seller->id, 'username' => $seller->username, 'avatar_url' => $seller->avatar_url] : null,
            'leader' => $leader ? ['id' => $leader->id, 'username' => $leader->username, 'avatar_url' => $leader->avatar_url] : null,
            'start_price' => (int) $a->start_price,
            'current_price' => $a->current_price ? (int) $a->current_price : null,
            'min_bid' => self::minBid($a),
            'bids_count' => (int) $a->bids_count,
            'cancel_penalty' => self::penaltyFor($a),
            'ends_at' => Carbon::parse($a->ends_at)->toIso8601String(),
            'messages_count' => $messages,
            'mine' => $viewer && $viewer->id === $a->seller_id,
            'leading' => $viewer && $viewer->id === $a->top_bidder_id,
        ];
    }
}
