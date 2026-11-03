<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetFarmTiers;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use App\Domains\Wallet\Services\RubleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Личная ферма — эндгейм за 500 000 ApexCoin. Здесь на грядках растут
 * редкие, эпические и легендарные питомцы (Феникс, Единорог), а ещё
 * открываются спаривание и скрещивание (PetBreedingService).
 *
 * Грядок три, питомец растёт на серверном таймере, клиент только
 * показывает. Семена — монетный сток, поэтому цена высокая.
 */
class PersonalFarmService
{
    public const PRICE = 500000;
    public const PLOTS = 3;
    /** Сколько семян можно посадить за сутки. */
    public const PLANTS_PER_DAY = 6;

    public function __construct(private readonly PetBreedingService $breeding)
    {
    }

    public static function has(User $user): bool
    {
        return DB::table('personal_farms')->where('user_id', $user->id)->exists();
    }

    public static function assertOwns(User $user): void
    {
        if (! self::has($user)) {
            throw new RuntimeException('Сначала открой Личную ферму.');
        }
    }

    public function state(User $user): array
    {
        $farm = DB::table('personal_farms')->where('user_id', $user->id)->first();

        $state = [
            'unlocked' => (bool) $farm,
            'price' => self::PRICE,
            'balance' => (int) $user->apex_coins,
            'rubles' => RubleService::balance($user),
            'speedup_per_hour' => RubleService::SPEEDUP_PER_HOUR,
            'seeds' => $this->seeds(),
            'limits' => [
                'mating_cost' => PetBreedingService::MATING_COST,
                'cross_cost' => PetBreedingService::CROSS_COST,
                'mating_hours' => PetBreedingService::MATING_HOURS,
                'energy_cost' => PetBreedingService::ENERGY_COST,
                'energy_max' => PetBreedingService::ENERGY_MAX,
                'max_in_storage' => PetBreedingService::MAX_IN_STORAGE,
                'mating_per_day' => PetBreedingService::MATING_PER_DAY,
                'cross_per_day' => PetBreedingService::CROSS_PER_DAY,
            ],
            'plots' => [],
            'plot_count' => (int) ($farm->plots ?? self::PLOTS),
            'storage' => [],
        ];

        if (! $farm) {
            return $state;
        }

        $state['plots'] = DB::table('farm_plots as p')
            ->join('shop_items as s', 's.id', '=', 'p.shop_item_id')
            ->where('p.user_id', $user->id)->whereNull('p.claimed_at')
            ->orderBy('p.ready_at')
            ->get(['p.id', 'p.tier', 'p.ready_at', 's.effect_value as species', 's.name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'tier' => $p->tier,
                'species' => $p->species,
                'name' => $p->name,
                'ready_at' => Carbon::parse($p->ready_at)->toIso8601String(),
                'ready' => Carbon::parse($p->ready_at)->isPast(),
            ])->all();

        $state['storage'] = $this->breeding->storage($user);
        $state['pets'] = $this->pets($user);

        return $state;
    }

    /** Открыть ферму. */
    public function unlock(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if (self::has($user)) {
                throw new RuntimeException('Личная ферма уже открыта.');
            }

            CoinService::debit($user, self::PRICE, CoinTransaction::SOURCE_PURCHASE, 'Личная ферма питомцев');

            DB::table('personal_farms')->insert([
                'user_id' => $user->id, 'plots' => self::PLOTS, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $this->state($user->fresh());
        });
    }

    /** Посадить семя питомца заданного вида. */
    public function plant(User $user, string $species): array
    {
        self::assertOwns($user);

        $tier = PetFarmTiers::tierOf($species);
        $seed = $tier ? (PetFarmTiers::PERSONAL_FARM[$tier] ?? null) : null;

        if (! $seed) {
            throw new RuntimeException('Такой вид на Личной ферме не растёт.');
        }

        $item = ShopItem::where('type', ShopItem::TYPE_PROFILE_PET)->where('effect_value', $species)->first();
        if (! $item) {
            throw new RuntimeException('Такого питомца пока нет.');
        }

        return DB::transaction(function () use ($user, $tier, $seed, $item) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $farm = DB::table('personal_farms')->where('user_id', $user->id)->first();

            $busy = DB::table('farm_plots')->where('user_id', $user->id)->whereNull('claimed_at')->count();
            if ($busy >= (int) $farm->plots) {
                throw new RuntimeException('Все грядки заняты — забери выросших питомцев.');
            }

            $today = DB::table('farm_plots')->where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
            if ($today >= self::PLANTS_PER_DAY) {
                throw new RuntimeException('Сегодня уже посажено ' . self::PLANTS_PER_DAY . ' семян — продолжай завтра.');
            }

            CoinService::debit($user, $seed['price'], CoinTransaction::SOURCE_PURCHASE, "Семя питомца: {$item->name}");

            DB::table('farm_plots')->insert([
                'user_id' => $user->id,
                'tier' => $tier,
                'shop_item_id' => $item->id,
                'traits' => json_encode(PetTraits::roll()),
                'ready_at' => now()->addHours($seed['hours']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->state($user->fresh());
        });
    }

    /** Забрать выросшего питомца с грядки. */
    public function harvest(User $user, int $plotId): array
    {
        self::assertOwns($user);

        return DB::transaction(function () use ($user, $plotId) {
            $plot = DB::table('farm_plots')->where('id', $plotId)->lockForUpdate()->first();

            if (! $plot || $plot->user_id !== $user->id || $plot->claimed_at) {
                throw new RuntimeException('Грядка не найдена.');
            }

            if (Carbon::parse($plot->ready_at)->isFuture()) {
                throw new RuntimeException('Питомец ещё растёт.');
            }

            $pet = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $plot->shop_item_id,
                'quantity' => 1,
                'pet_traits' => json_decode($plot->traits, true),
                'acquired_at' => now(),
            ]);

            DB::table('farm_plots')->where('id', $plotId)->update(['claimed_at' => now(), 'updated_at' => now()]);

            return [
                'pet' => ['inventory_id' => $pet->id, 'name' => $pet->item?->name, ...(PetTraits::present($pet->pet_traits, $pet->pet_serial) ?? [])],
            ];
        });
    }

    /** Ускорить рост на грядке за Рубли. */
    public function speedUp(User $user, int $plotId): array
    {
        self::assertOwns($user);

        return DB::transaction(function () use ($user, $plotId) {
            $plot = DB::table('farm_plots')->where('id', $plotId)->lockForUpdate()->first();

            if (! $plot || $plot->user_id !== $user->id || $plot->claimed_at) {
                throw new RuntimeException('Грядка не найдена.');
            }

            $cost = RubleService::speedUpCost($plot->ready_at);
            if ($cost === 0) {
                throw new RuntimeException('Питомец уже вырос.');
            }

            RubleService::spend($user, $cost, 'Ускорение грядки', "speedup:plot:{$plotId}");
            DB::table('farm_plots')->where('id', $plotId)->update(['ready_at' => now(), 'updated_at' => now()]);

            return ['spent' => $cost, 'rubles' => RubleService::balance($user)];
        });
    }

    /** Питомцы игрока для спаривания и скрещивания: ступень, уровень, энергия, занятость. */
    private function pets(User $user): array
    {
        $rows = UserInventory::with('item')->where('user_id', $user->id)->whereNotNull('pet_traits')->get();
        $ids = $rows->pluck('id')->all();
        $levels = PetService::levels($ids);
        $busy = \App\Domains\Players\Support\PetLocks::reasons($ids);

        return $rows->map(function (UserInventory $row) use ($levels, $busy) {
            $level = $levels[$row->id] ?? 1;
            $species = $row->item?->effect_value;
            $tier = PetFarmTiers::tierOf($species);

            return [
                'inventory_id' => $row->id,
                'species' => $species,
                'name' => $row->pet_name ?: $row->item?->name,
                'tier' => $tier,
                'tier_name' => $tier ? PetFarmTiers::NAMES[$tier] : null,
                'level' => $level,
                'energy' => PetBreedingService::energy($row, $level),
                'traits' => PetTraits::present($row->pet_traits, $row->pet_serial),
                'busy' => $busy[$row->id] ?? ($row->isEquipped() ? 'Питомец надет на профиль — сними его.' : null),
            ];
        })->sortBy([['tier_name', 'asc'], ['level', 'desc']])->values()->all();
    }

    /** Что можно посадить: вид, ступень, цена и срок. */
    private function seeds(): array
    {
        $items = ShopItem::where('type', ShopItem::TYPE_PROFILE_PET)->where('is_active', true)->get()->keyBy('effect_value');
        $seeds = [];

        foreach (PetFarmTiers::PERSONAL_FARM as $tier => $seed) {
            foreach (PetFarmTiers::SPECIES[$tier] as $species) {
                if ($item = $items->get($species)) {
                    $seeds[] = ['species' => $species, 'name' => $item->name, 'tier' => $tier, 'price' => $seed['price'], 'hours' => $seed['hours']];
                }
            }
        }

        return $seeds;
    }
}
