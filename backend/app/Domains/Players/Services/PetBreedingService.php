<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetFarmTiers;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Размножение питомцев на Личной ферме.
 *
 * Спаривание: два своих питомца уходят в «Кладовку» на сутки и
 * возвращаются с новым питомцем того же вида, что один из родителей
 * (шанс унаследовать окрас, фон и аксессуар). Каждое спаривание стоит
 * энергии, потом питомцу нужен отдых — иначе бесконечный фарм.
 *
 * Скрещивание: два питомца одной ступени сгорают, взамен один питомец
 * ступенью выше. Быстрый крафт, но родителей уже не вернуть.
 *
 * Оба действия — только между питомцами одного аккаунта и только для
 * свободных питомцев (PetLocks).
 */
class PetBreedingService
{
    public const MATING_COST = 2000;
    public const CROSS_COST = 1500;
    public const MATING_HOURS = 24;
    public const MAX_IN_STORAGE = 2;
    public const MATING_PER_DAY = 4;
    public const CROSS_PER_DAY = 5;

    public const ENERGY_MAX = 100;
    public const ENERGY_COST = 70;
    /** Очков энергии в час: с нуля до полной шкалы — 80 часов. */
    public const ENERGY_REGEN = 1.25;

    /** Шанс унаследовать черту у одного из родителей, иначе черта выпадает заново. */
    public const INHERIT_CHANCE = 0.75;
    public const INHERIT_CAP = 0.95;

    // ---- Энергия ----

    /** Энергия питомца сейчас: сохранённая + отдых с последней траты. */
    public static function energy(object $row, int $level = 1): int
    {
        $stored = (int) ($row->pet_energy ?? self::ENERGY_MAX);
        $since = $row->pet_energy_at ?? null;

        if (! $since || $stored >= self::ENERGY_MAX) {
            return min(self::ENERGY_MAX, $stored);
        }

        $hours = Carbon::parse($since)->diffInSeconds(now(), true) / 3600;

        return (int) min(self::ENERGY_MAX, floor($stored + $hours * self::ENERGY_REGEN * PetPerks::restFactor($level)));
    }

    // ---- Спаривание ----

    /** @return array<string, mixed> */
    public function mate(User $user, int $parent1Id, int $parent2Id): array
    {
        PersonalFarmService::assertOwns($user);

        if ($parent1Id === $parent2Id) {
            throw new RuntimeException('Нужны два разных питомца.');
        }

        return DB::transaction(function () use ($user, $parent1Id, $parent2Id) {
            // Блокируем игрока: параллельные запросы не обойдут лимиты
            User::whereKey($user->id)->lockForUpdate()->first();

            [$p1, $p2] = $this->lockedPair($user, [$parent1Id, $parent2Id]);
            $this->assertFree($p1);
            $this->assertFree($p2);

            $levels = PetService::levels([$p1->id, $p2->id]);
            $l1 = $levels[$p1->id] ?? 1;
            $l2 = $levels[$p2->id] ?? 1;

            foreach ([[$p1, $l1], [$p2, $l2]] as [$parent, $level]) {
                if (self::energy($parent, $level) < self::ENERGY_COST) {
                    throw new RuntimeException('Питомцу не хватает энергии — ему нужно отдохнуть.');
                }
            }

            $active = DB::table('pet_breedings')->where('user_id', $user->id)->where('kind', 'mating')->whereNull('claimed_at')->count();
            if ($active >= self::MAX_IN_STORAGE) {
                throw new RuntimeException('В Кладовке нет места: ' . self::MAX_IN_STORAGE . ' пары уже ждут возвращения.');
            }

            $today = DB::table('pet_breedings')->where('user_id', $user->id)->where('kind', 'mating')
                ->where('created_at', '>=', now()->startOfDay())->count();
            if ($today >= self::MATING_PER_DAY) {
                throw new RuntimeException('Сегодня уже было ' . self::MATING_PER_DAY . ' спаривания — продолжай завтра.');
            }

            CoinService::debit($user, self::MATING_COST, CoinTransaction::SOURCE_PURCHASE, 'Спаривание питомцев');

            foreach ([[$p1, $l1], [$p2, $l2]] as [$parent, $level]) {
                $parent->forceFill([
                    'pet_energy' => self::energy($parent, $level) - self::ENERGY_COST,
                    'pet_energy_at' => now(),
                ])->save();
            }

            $best = max($l1, $l2);
            $hours = self::MATING_HOURS - PetPerks::matingHoursOff($best);
            $species = random_int(0, 1) === 0 ? $p1->shop_item_id : $p2->shop_item_id;

            $id = DB::table('pet_breedings')->insertGetId([
                'user_id' => $user->id,
                'kind' => 'mating',
                'parent1_id' => $p1->id,
                'parent2_id' => $p2->id,
                'offspring_id' => null,
                'ends_at' => now()->addHours($hours),
                // Потомок определяется сейчас: перезаходом не перебросить
                'plan' => json_encode([
                    'shop_item_id' => $species,
                    'traits' => $this->blend($p1->pet_traits, $p2->pet_traits, $best),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->present(DB::table('pet_breedings')->find($id)) + ['balance' => (int) $user->fresh()->apex_coins];
        });
    }

    /** Забрать потомка из Кладовки. */
    public function claim(User $user, int $breedingId): array
    {
        return DB::transaction(function () use ($user, $breedingId) {
            $row = DB::table('pet_breedings')->where('id', $breedingId)->lockForUpdate()->first();

            if (! $row || $row->user_id !== $user->id || $row->kind !== 'mating' || $row->claimed_at) {
                throw new RuntimeException('Спаривание не найдено.');
            }

            if (! $row->ends_at || Carbon::parse($row->ends_at)->isFuture()) {
                throw new RuntimeException('Питомцы ещё в Кладовке.');
            }

            $plan = json_decode((string) $row->plan, true);

            $offspring = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $plan['shop_item_id'],
                'quantity' => 1,
                'pet_traits' => $plan['traits'],
                'acquired_at' => now(),
            ]);

            DB::table('pet_breedings')->where('id', $breedingId)->update([
                'offspring_id' => $offspring->id,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'offspring' => ['inventory_id' => $offspring->id, ...(PetTraits::present($offspring->pet_traits, $offspring->pet_serial) ?? [])],
                'species' => $offspring->item?->effect_value,
                'name' => $offspring->item?->name,
            ];
        });
    }

    // ---- Скрещивание ----

    /** @return array<string, mixed> */
    public function cross(User $user, int $parent1Id, int $parent2Id): array
    {
        PersonalFarmService::assertOwns($user);

        if ($parent1Id === $parent2Id) {
            throw new RuntimeException('Нужны два разных питомца.');
        }

        return DB::transaction(function () use ($user, $parent1Id, $parent2Id) {
            User::whereKey($user->id)->lockForUpdate()->first();

            [$p1, $p2] = $this->lockedPair($user, [$parent1Id, $parent2Id]);

            foreach ([$p1, $p2] as $parent) {
                $this->assertFree($parent);

                if ($parent->isEquipped()) {
                    throw new RuntimeException('Сначала сними питомца с профиля — скрещивание его сожжёт.');
                }

                if (collect($parent->pet_gear ?? [])->filter()->isNotEmpty()) {
                    throw new RuntimeException('Сними с питомца экипировку — скрещивание его сожжёт.');
                }
            }

            $tier = PetFarmTiers::tierOf($p1->item?->effect_value);
            if (! $tier || $tier !== PetFarmTiers::tierOf($p2->item?->effect_value)) {
                throw new RuntimeException('Скрещивать можно только питомцев одной ступени.');
            }

            $next = PetFarmTiers::next($tier);
            if (! $next) {
                throw new RuntimeException('Легендарных выше некуда — их можно только спаривать.');
            }

            $today = DB::table('pet_breedings')->where('user_id', $user->id)->where('kind', 'cross')
                ->where('created_at', '>=', now()->startOfDay())->count();
            if ($today >= self::CROSS_PER_DAY) {
                throw new RuntimeException('Сегодня уже было ' . self::CROSS_PER_DAY . ' скрещиваний — продолжай завтра.');
            }

            $species = PetFarmTiers::SPECIES[$next][array_rand(PetFarmTiers::SPECIES[$next])];
            $item = ShopItem::where('type', ShopItem::TYPE_PROFILE_PET)->where('effect_value', $species)->first();
            if (! $item) {
                throw new RuntimeException('Такого питомца пока нет.');
            }

            CoinService::debit($user, self::CROSS_COST, CoinTransaction::SOURCE_PURCHASE, 'Скрещивание питомцев');

            $traits = $this->blend($p1->pet_traits, $p2->pet_traits, 1);
            $ids = [$p1->id, $p2->id];

            UserInventory::whereIn('id', $ids)->delete();
            ShopService::syncPets($user);

            $offspring = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'quantity' => 1,
                'pet_traits' => $traits,
                'acquired_at' => now(),
            ]);

            DB::table('pet_breedings')->insert([
                'user_id' => $user->id,
                'kind' => 'cross',
                'parent1_id' => $ids[0],
                'parent2_id' => $ids[1],
                'offspring_id' => $offspring->id,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'offspring' => ['inventory_id' => $offspring->id, ...(PetTraits::present($offspring->pet_traits, $offspring->pet_serial) ?? [])],
                'species' => $species,
                'name' => $item->name,
                'tier' => $next,
                'balance' => (int) $user->fresh()->apex_coins,
            ];
        });
    }

    // ---- Кладовка ----

    /** Активные и готовые пары игрока. */
    public function storage(User $user): array
    {
        return DB::table('pet_breedings')->where('user_id', $user->id)->where('kind', 'mating')->whereNull('claimed_at')
            ->orderBy('ends_at')->get()->map(fn ($row) => $this->present($row))->all();
    }

    /** @return array<string, mixed> */
    private function present(object $row): array
    {
        $plan = json_decode((string) $row->plan, true) ?: [];
        $ready = $row->ends_at && Carbon::parse($row->ends_at)->isPast();

        return [
            'id' => $row->id,
            'parent1_id' => (int) $row->parent1_id,
            'parent2_id' => (int) $row->parent2_id,
            'started_at' => Carbon::parse($row->created_at)->toIso8601String(),
            'ends_at' => $row->ends_at ? Carbon::parse($row->ends_at)->toIso8601String() : null,
            'ready' => $ready && ! $row->claimed_at,
            'claimed' => (bool) $row->claimed_at,
            // Потомка видно только вернувшимся — как добычу экспедиции
            'offspring' => $ready && ! $row->claimed_at && isset($plan['shop_item_id'])
                ? ['traits' => PetTraits::present($plan['traits'])]
                : null,
        ];
    }

    // ---- Общее ----

    /**
     * Черты потомка: каждая — от случайного родителя с шансом унаследования,
     * иначе выпадает заново.
     *
     * @return array<string, string>
     */
    private function blend(?array $a, ?array $b, int $level): array
    {
        $a = $a ?: [];
        $b = $b ?: [];
        $chance = min(self::INHERIT_CAP, self::INHERIT_CHANCE + PetPerks::heritageBonus($level));
        $fresh = PetTraits::roll();
        $out = [];

        foreach (['model', 'backdrop', 'accessory'] as $trait) {
            $from = random_int(0, 1) === 0 ? $a : $b;
            $inherit = random_int(1, 10000) <= (int) round($chance * 10000);

            $out[$trait] = $inherit && isset($from[$trait]) ? $from[$trait] : ($fresh[$trait] ?? 'none');
        }

        return $out;
    }

    /** Два своих питомца под замком строки; чужой или не питомец — ошибка. */
    private function lockedPair(User $user, array $ids): array
    {
        $rows = UserInventory::with('item')->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

        return array_map(function (int $id) use ($user, $rows) {
            $row = $rows->get($id);

            if (! $row || $row->user_id !== $user->id || $row->item?->type !== ShopItem::TYPE_PROFILE_PET) {
                throw new RuntimeException('Это не твой питомец.');
            }

            return $row;
        }, $ids);
    }

    private function assertFree(UserInventory $pet): void
    {
        if ($busy = PetLocks::reason($pet->id)) {
            throw new RuntimeException($busy);
        }
    }
}
