<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetFarmTiers;
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
 * Общая ферма APEX: доступна всем, питомцы спавнятся и растут сами.
 *
 * Бронь: пока питомец растёт (или уже вырос), можно внести задаток —
 * тогда он ждёт тебя HOLD_HOURS часов. Не выкупил вовремя — бронь
 * слетает, задаток сгорает (это защита от «забил — и забыл»).
 * Выкуп: выросшего питомца можно купить сразу за полную цену или
 * доплатить за забронированного.
 *
 * Спавн, рост и снятие брони — серверный планировщик (farm:tick):
 * клиент только показывает.
 */
class PetFarmService
{
    /** Сколько питомцев каждой ступени держит ферма на витрине. */
    public const STOCK = ['common' => 8, 'uncommon' => 4, 'rare' => 1];

    public const DEPOSIT_SHARE = 0.25;
    public const HOLD_HOURS = 24;
    public const MAX_RESERVATIONS = 3;
    public const BUYS_PER_DAY = 6;
    /** Сколько часов выросший питомец ждёт покупателя, потом уходит с фермы. */
    public const SHELF_HOURS = 48;

    // ---- Витрина ----

    public function state(User $user): array
    {
        $this->seedIfEmpty();

        $now = now();
        $rows = DB::table('farm_pets as f')
            ->join('shop_items as s', 's.id', '=', 'f.shop_item_id')
            ->where('f.status', '!=', 'sold')
            ->where('f.expires_at', '>', $now)
            ->orderBy('f.ready_at')
            ->get(['f.*', 's.effect_value as species', 's.name']);

        $pets = $rows->map(function ($f) use ($user, $now) {
            $held = $f->status === 'reserved' && Carbon::parse($f->reserved_until)->gt($now);
            $mine = $held && (int) $f->reserved_by === $user->id;
            $taken = $held && ! $mine;
            $ready = Carbon::parse($f->ready_at)->lte($now);

            return [
                'id' => $f->id,
                'tier' => $f->tier,
                'tier_name' => PetFarmTiers::NAMES[$f->tier],
                'species' => $f->species,
                'name' => $f->name,
                'traits' => PetTraits::present(json_decode($f->traits, true)),
                'price' => (int) $f->price,
                'deposit' => $this->deposit((int) $f->price),
                'ready' => $ready,
                'spawned_at' => Carbon::parse($f->created_at)->toIso8601String(),
                'ready_at' => Carbon::parse($f->ready_at)->toIso8601String(),
                'expires_at' => Carbon::parse($f->expires_at)->toIso8601String(),
                'reserved' => $taken || $mine,
                'mine' => $mine,
                'reserved_until' => $mine ? Carbon::parse($f->reserved_until)->toIso8601String() : null,
                'to_pay' => $mine ? (int) $f->price - (int) $f->deposit : (int) $f->price,
            ];
        })->values()->all();

        return [
            'pets' => $pets,
            'balance' => (int) $user->apex_coins,
            'hold_hours' => self::HOLD_HOURS,
            'max_reservations' => self::MAX_RESERVATIONS,
            'reservations' => collect($pets)->where('mine', true)->count(),
            'buys_left' => max(0, self::BUYS_PER_DAY - $this->boughtToday($user)),
            'buys_per_day' => self::BUYS_PER_DAY,
            'deposit_share' => self::DEPOSIT_SHARE,
        ];
    }

    // ---- Игрок ----

    public function reserve(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $pet = $this->lock($id);

            if ($pet->status === 'reserved') {
                throw new RuntimeException((int) $pet->reserved_by === $user->id ? 'Ты уже забронировал этого питомца.' : 'Этого питомца уже забронировали.');
            }

            $mine = DB::table('farm_pets')->where('status', 'reserved')->where('reserved_by', $user->id)->count();
            if ($mine >= self::MAX_RESERVATIONS) {
                throw new RuntimeException('Не больше ' . self::MAX_RESERVATIONS . ' броней одновременно.');
            }

            $deposit = $this->deposit((int) $pet->price);
            CoinService::debit($user, $deposit, CoinTransaction::SOURCE_PURCHASE, 'Бронь питомца на Общей ферме');

            DB::table('farm_pets')->where('id', $id)->update([
                'status' => 'reserved',
                'reserved_by' => $user->id,
                'reserved_until' => now()->addHours(self::HOLD_HOURS),
                'deposit' => $deposit,
                // Питомца, которого держат в брони, не убираем с фермы раньше срока
                'expires_at' => Carbon::parse($pet->expires_at)->max(now()->addHours(self::HOLD_HOURS + 1)),
                'updated_at' => now(),
            ]);

            return $this->state($user->fresh());
        });
    }

    /** Выкупить выросшего питомца (свою бронь — с доплатой). */
    public function buy(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $pet = $this->lock($id);

            if (Carbon::parse($pet->ready_at)->isFuture()) {
                throw new RuntimeException('Питомец ещё растёт.');
            }

            $mine = $pet->status === 'reserved' && (int) $pet->reserved_by === $user->id;

            if ($pet->status === 'reserved' && ! $mine) {
                throw new RuntimeException('Этого питомца уже забронировали.');
            }

            if ($this->boughtToday($user) >= self::BUYS_PER_DAY) {
                throw new RuntimeException('Сегодня уже ' . self::BUYS_PER_DAY . ' покупок на ферме — приходи завтра.');
            }

            $pay = (int) $pet->price - ($mine ? (int) $pet->deposit : 0);
            CoinService::debit($user, $pay, CoinTransaction::SOURCE_PURCHASE, 'Выкуп питомца на Общей ферме');

            $row = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $pet->shop_item_id,
                'quantity' => 1,
                'pet_traits' => json_decode($pet->traits, true),
                'acquired_at' => now(),
            ]);

            DB::table('farm_pets')->where('id', $id)->update([
                'status' => 'sold',
                'sold_to' => $user->id,
                'sold_at' => now(),
                'reserved_by' => null,
                'reserved_until' => null,
                'updated_at' => now(),
            ]);

            return [
                'pet' => ['inventory_id' => $row->id, 'name' => $row->item?->name, ...(PetTraits::present($row->pet_traits, $row->pet_serial) ?? [])],
                'paid' => $pay,
                'farm' => $this->state($user->fresh()),
            ];
        });
    }

    // ---- Серверные таймеры ----

    /**
     * Раз в минуту: снимаем просроченные брони (задаток не возвращается),
     * убираем невостребованных, подращиваем витрину.
     *
     * @return array{lapsed: int, removed: int, spawned: int}
     */
    public function tick(): array
    {
        $lapsed = DB::table('farm_pets')->where('status', 'reserved')->where('reserved_until', '<=', now())
            ->update(['status' => 'growing', 'reserved_by' => null, 'reserved_until' => null, 'deposit' => 0, 'updated_at' => now()]);

        // Раз в сутки чистим и старые проданные записи
        $removed = DB::table('farm_pets')->where('status', 'growing')->where('expires_at', '<=', now())->delete();
        DB::table('farm_pets')->where('status', 'sold')->where('sold_at', '<', now()->subDays(7))->delete();

        $spawned = 0;
        foreach (self::STOCK as $tier => $target) {
            $have = DB::table('farm_pets')->where('tier', $tier)->where('status', '!=', 'sold')->where('expires_at', '>', now())->count();
            // По одному за такт: витрина наполняется постепенно, а не пачкой
            if ($have < $target && $this->spawn($tier)) {
                $spawned++;
            }
        }

        return compact('lapsed', 'removed', 'spawned');
    }

    /** Пустую ферму (первый запуск) наполняем сразу. */
    private function seedIfEmpty(): void
    {
        if (DB::table('farm_pets')->where('status', '!=', 'sold')->where('expires_at', '>', now())->exists()) {
            return;
        }

        foreach (self::STOCK as $tier => $target) {
            for ($i = 0; $i < $target; $i++) {
                $this->spawn($tier);
            }
        }
    }

    private function spawn(string $tier): bool
    {
        $species = PetFarmTiers::SPECIES[$tier];
        $item = ShopItem::where('type', ShopItem::TYPE_PROFILE_PET)->where('is_active', true)
            ->whereIn('effect_value', $species)->inRandomOrder()->first();

        if (! $item) {
            return false;
        }

        [$from, $to] = PetFarmTiers::COMMON_FARM_GROWTH[$tier];
        $ready = now()->addMinutes(random_int($from * 60, $to * 60));

        DB::table('farm_pets')->insert([
            'tier' => $tier,
            'shop_item_id' => $item->id,
            'traits' => json_encode(PetTraits::roll()),
            'price' => PetFarmTiers::COMMON_FARM_PRICE[$tier],
            'status' => 'growing',
            'ready_at' => $ready,
            'expires_at' => $ready->copy()->addHours(self::SHELF_HOURS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function deposit(int $price): int
    {
        return (int) ceil($price * self::DEPOSIT_SHARE);
    }

    private function boughtToday(User $user): int
    {
        return DB::table('farm_pets')->where('sold_to', $user->id)->where('sold_at', '>=', now()->startOfDay())->count();
    }

    private function lock(int $id): object
    {
        $pet = DB::table('farm_pets')->where('id', $id)->lockForUpdate()->first();

        if (! $pet || $pet->status === 'sold' || Carbon::parse($pet->expires_at)->isPast()) {
            throw new RuntimeException('Питомца уже нет на ферме.');
        }

        // Планировщик мог ещё не снять просроченную бронь — снимаем на месте
        if ($pet->status === 'reserved' && Carbon::parse($pet->reserved_until)->isPast()) {
            DB::table('farm_pets')->where('id', $id)->update(['status' => 'growing', 'reserved_by' => null, 'reserved_until' => null, 'deposit' => 0, 'updated_at' => now()]);
            $pet->status = 'growing';
            $pet->reserved_by = null;
            $pet->deposit = 0;
        }

        return $pet;
    }
}
