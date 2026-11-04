<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Рынок игроков: материалы и снаряжение подземелий.
 *
 * Товар лежит в эскроу — списан с продавца при выставлении лота, так что
 * один и тот же предмет нельзя продать дважды. Покупка: строка лота под
 * замком, монеты списываются у покупателя, продавец получает цену минус
 * комиссию (сток для экономики). Лот живёт 72 часа, потом возвращается.
 * Питомцы продаются только на аукционе, сюда они не попадают.
 */
class MarketService
{
    public const FEE_SHARE = 0.05;
    public const MAX_ACTIVE = 10;
    public const HOURS = 72;
    public const MIN_PRICE = 1;
    public const MAX_PRICE = 1000000;
    public const MAX_QTY = 99;
    /** Новичкам торговать нельзя первую неделю — как с подарками. */
    public const MIN_ACCOUNT_DAYS = 7;

    // ---- Витрина ----

    /** Активные лоты (чужие) и свои. */
    public function state(User $user, string $kind = 'all'): array
    {
        $this->expireDue();

        $query = DB::table('market_listings as m')
            ->join('users as u', 'u.id', '=', 'm.seller_id')
            ->where('m.status', 'active')
            ->where('m.expires_at', '>', now())
            ->when(in_array($kind, ['material', 'gear'], true), fn ($q) => $q->where('m.kind', $kind))
            ->orderBy('m.price')
            ->limit(80)
            ->get(['m.*', 'u.username']);

        $rows = $query->map(fn ($m) => $this->present($m, $user))->values();

        return [
            'listings' => $rows->where('mine', false)->values()->all(),
            'mine' => $rows->where('mine', true)->values()->all(),
            'fee_share' => self::FEE_SHARE,
            'max_active' => self::MAX_ACTIVE,
            'hours' => self::HOURS,
            'min_account_days' => self::MIN_ACCOUNT_DAYS,
            'balance' => (int) $user->apex_coins,
        ];
    }

    // ---- Продажа ----

    public function sellMaterial(User $user, string $material, int $qty, int $price): array
    {
        $this->assertCanSell($user, $price);

        if (! isset(Dungeons::MATERIALS[$material])) {
            throw new RuntimeException('Такого материала нет.');
        }

        if (! empty(Dungeons::MATERIALS[$material]['bound'])) {
            throw new RuntimeException('Этот предмет привязан к аккаунту и не продаётся.');
        }

        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw new RuntimeException('Количество — от 1 до ' . self::MAX_QTY . '.');
        }

        return DB::transaction(function () use ($user, $material, $qty, $price) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->assertActiveLimit($user);

            $row = DB::table('dungeon_materials')->where('user_id', $user->id)->where('material', $material)->lockForUpdate()->first();

            if (! $row || (int) $row->quantity < $qty) {
                throw new RuntimeException('Столько материала у тебя нет.');
            }

            $left = (int) $row->quantity - $qty;
            $left > 0
                ? DB::table('dungeon_materials')->where('id', $row->id)->update(['quantity' => $left, 'updated_at' => now()])
                : DB::table('dungeon_materials')->where('id', $row->id)->delete();

            $id = $this->create($user, 'material', $material, $qty, null, $price);

            return ['id' => $id, ...$this->state($user)];
        });
    }

    public function sellGear(User $user, int $inventoryId, int $price): array
    {
        $this->assertCanSell($user, $price);

        return DB::transaction(function () use ($user, $inventoryId, $price) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->assertActiveLimit($user);
            UserInventory::whereKey($inventoryId)->lockForUpdate()->get();

            $item = collect(app(PetGearService::class)->bag($user))->firstWhere('inventory_id', $inventoryId);

            if (! $item) {
                throw new RuntimeException('Предмет не найден.');
            }

            if ($item['worn_by']) {
                throw new RuntimeException('Сначала сними предмет с питомца.');
            }

            $row = UserInventory::findOrFail($inventoryId);
            $payload = ['shop_item_id' => $row->shop_item_id, 'gear_stats' => $row->gear_stats];

            // Предмет уходит в эскроу: из инвентаря убран, вернётся при отмене
            $row->delete();

            $id = $this->create($user, 'gear', $item['slug'], 1, $payload, $price);

            return ['id' => $id, ...$this->state($user)];
        });
    }

    // ---- Покупка и отмена ----

    public function buy(User $buyer, int $id): array
    {
        return DB::transaction(function () use ($buyer, $id) {
            User::whereKey($buyer->id)->lockForUpdate()->first();
            $listing = DB::table('market_listings')->where('id', $id)->lockForUpdate()->first();

            if (! $listing || $listing->status !== 'active' || Carbon::parse($listing->expires_at)->isPast()) {
                throw new RuntimeException('Лот уже недоступен.');
            }

            if ((int) $listing->seller_id === $buyer->id) {
                throw new RuntimeException('Свой лот купить нельзя.');
            }

            $seller = User::findOrFail($listing->seller_id);
            $fee = (int) floor($listing->price * self::FEE_SHARE);

            CoinService::debit($buyer, (int) $listing->price, CoinTransaction::SOURCE_PURCHASE, 'Рынок: ' . $this->name($listing));
            CoinService::credit($seller, (int) $listing->price - $fee, CoinTransaction::SOURCE_OTHER, 'Рынок: продажа ' . $this->name($listing), "market:{$id}:seller");
            QuestService::track($seller, 'market_sell');

            $this->deliver($buyer, $listing);

            DB::table('market_listings')->where('id', $id)->update([
                'status' => 'sold', 'buyer_id' => $buyer->id, 'fee' => $fee, 'sold_at' => now(), 'updated_at' => now(),
            ]);

            return ['bought' => $this->name($listing), 'paid' => (int) $listing->price, ...$this->state($buyer->fresh())];
        });
    }

    public function cancel(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $listing = DB::table('market_listings')->where('id', $id)->lockForUpdate()->first();

            if (! $listing || (int) $listing->seller_id !== $user->id || $listing->status !== 'active') {
                throw new RuntimeException('Лот не найден.');
            }

            $this->deliver($user, $listing);
            DB::table('market_listings')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

            return $this->state($user);
        });
    }

    /** Планировщик и витрина: просроченные лоты возвращаются продавцам. */
    public function expireDue(): int
    {
        $due = DB::table('market_listings')->where('status', 'active')->where('expires_at', '<=', now())->pluck('id');

        foreach ($due as $id) {
            DB::transaction(function () use ($id) {
                $listing = DB::table('market_listings')->where('id', $id)->lockForUpdate()->first();

                if (! $listing || $listing->status !== 'active') {
                    return;
                }

                $this->deliver(User::findOrFail($listing->seller_id), $listing);
                DB::table('market_listings')->where('id', $id)->update(['status' => 'expired', 'updated_at' => now()]);
            });
        }

        return $due->count();
    }

    // ---- Внутреннее ----

    private function assertCanSell(User $user, int $price): void
    {
        if ($user->created_at && $user->created_at->gt(now()->subDays(self::MIN_ACCOUNT_DAYS))) {
            throw new RuntimeException('Первую неделю после регистрации торговать на рынке нельзя.');
        }

        if ($price < self::MIN_PRICE || $price > self::MAX_PRICE) {
            throw new RuntimeException('Цена — от ' . self::MIN_PRICE . ' до ' . number_format(self::MAX_PRICE, 0, '', ' ') . ' ApexCoin.');
        }
    }

    private function assertActiveLimit(User $user): void
    {
        $active = DB::table('market_listings')->where('seller_id', $user->id)->where('status', 'active')->count();

        if ($active >= self::MAX_ACTIVE) {
            throw new RuntimeException('На рынке не больше ' . self::MAX_ACTIVE . ' твоих лотов.');
        }
    }

    private function create(User $user, string $kind, string $ref, int $qty, ?array $payload, int $price): int
    {
        return DB::table('market_listings')->insertGetId([
            'seller_id' => $user->id,
            'kind' => $kind,
            'ref' => $ref,
            'qty' => $qty,
            'payload' => $payload ? json_encode($payload) : null,
            'price' => $price,
            'status' => 'active',
            'expires_at' => now()->addHours(self::HOURS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Отдать товар лота владельцу: покупателю при продаже, продавцу при возврате. */
    private function deliver(User $to, object $listing): void
    {
        if ($listing->kind === 'material') {
            DB::table('dungeon_materials')->upsert(
                ['user_id' => $to->id, 'material' => $listing->ref, 'quantity' => $listing->qty, 'created_at' => now(), 'updated_at' => now()],
                ['user_id', 'material'],
                ['quantity' => DB::raw('quantity + ' . (int) $listing->qty)],
            );

            return;
        }

        $payload = json_decode((string) $listing->payload, true);

        UserInventory::create([
            'user_id' => $to->id,
            'shop_item_id' => $payload['shop_item_id'],
            'quantity' => 1,
            'acquired_at' => now(),
            'gear_stats' => $payload['gear_stats'] ?? null,
        ]);
    }

    private function name(object $listing): string
    {
        $def = $listing->kind === 'material' ? (Dungeons::MATERIALS[$listing->ref] ?? null) : (Dungeons::GEAR[$listing->ref] ?? null);

        return ($def['name'] ?? $listing->ref) . ($listing->qty > 1 ? " × {$listing->qty}" : '');
    }

    /** @return array<string, mixed> */
    private function present(object $m, User $viewer): array
    {
        $payload = json_decode((string) $m->payload, true) ?: [];
        $def = $m->kind === 'material' ? (Dungeons::MATERIALS[$m->ref] ?? []) : (Dungeons::GEAR[$m->ref] ?? []);
        $rarity = $payload['gear_stats']['rarity'] ?? null;

        return [
            'id' => $m->id,
            'kind' => $m->kind,
            'ref' => $m->ref,
            'name' => $def['name'] ?? $m->ref,
            'sprite' => $def['sprite'] ?? null,
            'qty' => (int) $m->qty,
            'price' => (int) $m->price,
            'unit' => $m->qty > 0 ? (int) ceil($m->price / $m->qty) : (int) $m->price,
            'rarity' => $rarity,
            'rarity_name' => $rarity ? Dungeons::rarityName($rarity) : null,
            'slot' => $def['slot'] ?? null,
            'stats' => $m->kind === 'gear' ? ($payload['gear_stats'] ?? null) : null,
            'seller' => $m->username,
            'mine' => (int) $m->seller_id === $viewer->id,
            'expires_at' => Carbon::parse($m->expires_at)->toIso8601String(),
        ];
    }
}
