<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Экспедиции: питомец уходит в поход на несколько часов и приносит
 * монеты, опыт и иногда ключи от сундуков.
 *
 * Добыча выкатывается при отправке (переоткрытием не перебросить) и
 * видна только по возвращении. Чем выше уровень, тем богаче добыча;
 * дальние земли открываются с уровнем.
 */
class PetExpeditionService
{
    /**
     * Зона => название, часы, мин. уровень, монеты [от, до], опыт,
     * шанс ключа «Сундука искателя» и «Королевского сундука» в процентах.
     */
    public const ZONES = [
        'meadow' => ['name' => 'Цветочный луг', 'hours' => 1, 'min_level' => 1, 'coins' => [120, 200], 'xp' => 3, 'key_chance' => 4, 'royal_key_chance' => 0.15],
        'forest' => ['name' => 'Тёмный лес', 'hours' => 4, 'min_level' => 3, 'coins' => [550, 800], 'xp' => 8, 'key_chance' => 12, 'royal_key_chance' => 0.5],
        'caves' => ['name' => 'Кристальные пещеры', 'hours' => 8, 'min_level' => 8, 'coins' => [1200, 1700], 'xp' => 15, 'key_chance' => 25, 'royal_key_chance' => 1.25],
        'nether' => ['name' => 'Огненные земли', 'hours' => 12, 'min_level' => 15, 'coins' => [2000, 2800], 'xp' => 24, 'key_chance' => 40, 'royal_key_chance' => 2.5],
    ];

    /** Монеты растут на 2% за каждый уровень питомца. */
    private const LEVEL_SHARE = 0.02;

    /** Находки для рассказа о походе — без влияния на награду. */
    private const STORIES = [
        'meadow' => ['поймал бабочку и отпустил', 'нашёл четырёхлистный клевер', 'поспал в ромашках', 'подружился с пчёлкой'],
        'forest' => ['распугал летучих мышей', 'нашёл старый компас', 'обошёл паука стороной', 'принёс шишку на память'],
        'caves' => ['видел сияющие кристаллы', 'прокрался мимо скелета', 'нашёл отпечаток алмаза', 'заблудился, но выбрался'],
        'nether' => ['уклонился от гаста', 'прошёл по мосту над лавой', 'стащил золото у пиглина', 'чуть не подпалил хвост'],
    ];

    public function zones(): array
    {
        return collect(self::ZONES)->map(fn ($z, $key) => ['key' => $key, ...$z])->values()->all();
    }

    public function start(User $user, int $inventoryId, string $zone): array
    {
        $config = self::ZONES[$zone] ?? null;
        if (! $config) {
            throw new RuntimeException('Такой земли нет.');
        }

        $row = $this->ownPet($user, $inventoryId);
        $level = PetService::levels([$row->id])[$row->id] ?? 1;

        if ($level < $config['min_level']) {
            throw new RuntimeException("{$config['name']} открываются с {$config['min_level']} уровня питомца.");
        }

        if ($busy = PetLocks::reason($row->id)) {
            throw new RuntimeException($busy);
        }

        $id = DB::table('pet_expeditions')->insertGetId([
            'user_id' => $user->id,
            'inventory_id' => $row->id,
            'zone' => $zone,
            'ends_at' => now()->addSeconds((int) round($config['hours'] * 3600 * PetPerks::expeditionTimeFactor($level))),
            'reward' => json_encode($this->roll($config, $zone, $level)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->present(DB::table('pet_expeditions')->find($id));
    }

    /** Забрать добычу вернувшегося питомца. */
    public function claim(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $trip = DB::table('pet_expeditions')->lockForUpdate()->find($id);

            if (! $trip || $trip->user_id !== $user->id || $trip->claimed_at) {
                throw new RuntimeException('Экспедиция не найдена.');
            }

            if (Carbon::parse($trip->ends_at)->isFuture()) {
                throw new RuntimeException('Питомец ещё в пути.');
            }

            $reward = json_decode($trip->reward, true);

            DB::table('pet_expeditions')->where('id', $id)->update(['claimed_at' => now(), 'updated_at' => now()]);

            // Питомца могли продать по пути? Нельзя — он занят. Но строку могли удалить
            DB::table('user_inventory')->where('id', $trip->inventory_id)->increment('pet_xp', $reward['xp']);

            if ($reward['coins'] > 0) {
                CoinService::credit($user, $reward['coins'], CoinTransaction::SOURCE_OTHER, 'Экспедиция питомца: ' . self::ZONES[$trip->zone]['name'], "pet-expedition:{$id}");
            }

            foreach ($reward['keys'] as $slug => $count) {
                LootCaseService::grantKeys($user, $slug, $count);
            }

            return $this->present(DB::table('pet_expeditions')->find($id)) + ['balance' => (int) $user->fresh()->apex_coins];
        });
    }

    /**
     * Вернуть питомца досрочно.
     *
     * Награда НЕ выдаётся, даже если до конца оставалась минута: отозвали —
     * значит поход не дослужил. Питомец освобождается и может пойти снова.
     */
    public function recall(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $trip = DB::table('pet_expeditions')->lockForUpdate()->find($id);

            if (! $trip || $trip->user_id !== $user->id) {
                throw new RuntimeException('Экспедиция не найдена.');
            }

            if ($trip->claimed_at || $trip->recalled_at) {
                throw new RuntimeException('Питомец уже вернулся.');
            }

            $now = now();

            DB::table('pet_expeditions')->where('id', $id)->update([
                'claimed_at' => $now,
                'recalled_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->present(DB::table('pet_expeditions')->find($id));
        });
    }

    /** Активные и невостребованные экспедиции игрока. */
    public function active(User $user): array
    {
        return DB::table('pet_expeditions')
            ->where('user_id', $user->id)
            ->whereNull('claimed_at')
            ->orderBy('ends_at')
            ->get()
            ->map(fn ($trip) => $this->present($trip))
            ->all();
    }

    private function roll(array $config, string $zone, int $level): array
    {
        [$min, $max] = $config['coins'];
        $coins = random_int($min, $max);
        $coins = (int) round($coins * (1 + min($level, PetPerks::COIN_SCALING_CAP) * self::LEVEL_SHARE) * (1 + PetPerks::expeditionCoinsBonus($level)));

        $double = $this->chance(PetPerks::doubleLootChance($level));
        if ($double) {
            $coins *= 2;
        }

        $keys = [];
        if ($this->chance($config['key_chance'] + PetPerks::keyChanceBonus($level))) {
            $keys['seeker'] = $double ? 2 : 1;
        }
        if ($this->chance($config['royal_key_chance'])) {
            $keys['royal'] = 1;
        }

        $stories = self::STORIES[$zone];

        return [
            'coins' => $coins,
            'xp' => $config['xp'] * PetService::xpMultiplier($level),
            'keys' => $keys,
            'double' => $double,
            'story' => $stories[array_rand($stories)],
        ];
    }

    private function chance(float $percent): bool
    {
        return $percent > 0 && random_int(1, 10000) <= (int) round($percent * 100);
    }

    private function present(object $trip): array
    {
        $done = Carbon::parse($trip->ends_at)->isPast();

        return [
            'id' => $trip->id,
            'inventory_id' => $trip->inventory_id,
            'zone' => $trip->zone,
            'zone_name' => self::ZONES[$trip->zone]['name'] ?? $trip->zone,
            'started_at' => Carbon::parse($trip->created_at)->toIso8601String(),
            'ends_at' => Carbon::parse($trip->ends_at)->toIso8601String(),
            'ready' => $done && ! $trip->claimed_at,
            'claimed' => (bool) $trip->claimed_at,
            'recalled' => (bool) ($trip->recalled_at ?? false),
            // Добыча — только когда питомец вернулся
            'reward' => $done ? json_decode($trip->reward, true) : null,
        ];
    }

    public function ownPet(User $user, int $inventoryId): UserInventory
    {
        $row = UserInventory::with('item')->find($inventoryId);

        if (! $row || $row->user_id !== $user->id || $row->item?->type !== ShopItem::TYPE_PROFILE_PET) {
            throw new RuntimeException('Это не твой питомец.');
        }

        return $row;
    }
}
