<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetNames;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * «Мои питомцы»: сводка по всем питомцам игрока, трюки, питомцедекс
 * и топ питомцев сервера.
 */
class PetHubService
{
    /** Трюк => уровень, с которого открывается, и название. */
    public const TRICKS = [
        'spin' => [3, 'Волчок'],
        'flip' => [6, 'Сальто'],
        'dance' => [10, 'Танец'],
        'wave' => [14, 'Привет всем'],
        'hearts' => [18, 'Сердечки'],
        'sparkle' => [22, 'Звездопад'],
        'rainbow' => [26, 'Радуга'],
        'firework' => [30, 'Фейерверк'],
    ];

    /** Награды питомцедекса: вехи => [что считаем, сколько нужно, монеты, ключи]. */
    public const MILESTONES = [
        'species_3' => ['kind' => 'species', 'need' => 3, 'coins' => 1000, 'title' => '3 вида питомцев'],
        'species_6' => ['kind' => 'species', 'need' => 6, 'coins' => 2500, 'title' => '6 видов питомцев'],
        'species_10' => ['kind' => 'species', 'need' => 10, 'coins' => 5000, 'title' => '10 видов питомцев'],
        'species_15' => ['kind' => 'species', 'need' => 15, 'coins' => 10000, 'title' => '15 видов питомцев'],
        'species_all' => ['kind' => 'species', 'need' => 'all', 'coins' => 25000, 'keys' => ['pets' => 3], 'title' => 'Все виды питомцев'],
        'model_10' => ['kind' => 'model', 'need' => 10, 'coins' => 2000, 'title' => '10 окрасов'],
        'model_all' => ['kind' => 'model', 'need' => 'all', 'coins' => 15000, 'title' => 'Все окрасы'],
        'backdrop_10' => ['kind' => 'backdrop', 'need' => 10, 'coins' => 2000, 'title' => '10 фонов'],
        'backdrop_all' => ['kind' => 'backdrop', 'need' => 'all', 'coins' => 12000, 'title' => 'Все фоны'],
        'accessory_10' => ['kind' => 'accessory', 'need' => 10, 'coins' => 2000, 'title' => '10 аксессуаров'],
        'accessory_all' => ['kind' => 'accessory', 'need' => 'all', 'coins' => 15000, 'title' => 'Все аксессуары'],
    ];

    public function __construct(
        private readonly PetExpeditionService $expeditions,
        private readonly PetArenaService $arena,
    ) {
    }

    /** Всё для страницы «Мои питомцы». */
    public function overview(User $user): array
    {
        $rows = $this->pets($user);
        $xp = PetService::xpTotals($rows->pluck('id')->all());
        $trips = collect($this->expeditions->active($user))->keyBy('inventory_id');

        // Уход: что хозяин уже сделал сегодня и когда питомца кормили
        $ids = $rows->pluck('id')->all();
        $done = DB::table('pet_interactions')->whereIn('inventory_id', $ids ?: [0])
            ->where('actor_id', $user->id)->where('day', today()->toDateString())
            ->get(['inventory_id', 'action'])->groupBy('inventory_id');
        $fed = DB::table('pet_interactions')->whereIn('inventory_id', $ids ?: [0])->where('action', 'feed')
            ->groupBy('inventory_id')->selectRaw('inventory_id, max(created_at) as at')->pluck('at', 'inventory_id');

        $pets = $rows->map(function (UserInventory $row) use ($xp, $trips, $done, $fed) {
            $points = $xp[$row->id] ?? 0;
            $level = PetService::levelOf($points);

            return [
                ...$this->arena->card($row, $level),
                'xp' => $points,
                'equipped' => $row->isEquipped(),
                'trick' => $row->pet_trick,
                'perks' => PetPerks::present($level),
                'expedition' => $trips->get($row->id),
                'busy' => PetLocks::reason($row->id),
                'care' => ($done[$row->id] ?? collect())->pluck('action')->values()->all(),
                'hungry' => ! isset($fed[$row->id]) || now()->diffInHours($fed[$row->id], true) >= 24,
            ];
        })->sortByDesc('level')->values()->all();

        return [
            'pets' => $pets,
            'zones' => $this->expeditions->zones(),
            'tricks' => collect(self::TRICKS)->map(fn ($t, $key) => ['key' => $key, 'level' => $t[0], 'name' => $t[1]])->values()->all(),
            'perks' => collect(PetPerks::LIST)->map(fn ($p, $level) => ['level' => $level, 'key' => $p[0], 'label' => $p[1]])->values()->all(),
            'arena' => [
                'fights_left' => $this->arena->fightsLeft($user),
                'active' => $this->arena->active($user),
                'skills' => PetArenaService::SKILLS,
            ],
        ];
    }

    public function setTrick(User $user, int $inventoryId, ?string $trick): array
    {
        $row = $this->expeditions->ownPet($user, $inventoryId);

        if ($trick !== null) {
            $config = self::TRICKS[$trick] ?? null;
            if (! $config) {
                throw new RuntimeException('Такого трюка нет.');
            }

            $level = PetService::levels([$row->id])[$row->id] ?? 1;
            if ($level < $config[0]) {
                throw new RuntimeException("«{$config[1]}» питомец выучит на {$config[0]} уровне.");
            }
        }

        $row->forceFill(['pet_trick' => $trick])->save();
        // Трюк виден в профиле — обновляем гуляющих
        ShopService::syncPets($user);

        return ['trick' => $trick];
    }

    // ---- Питомцедекс ----

    /** Отметить в питомцедексе вид и черты питомца нового владельца. */
    public static function record(UserInventory $row): void
    {
        $species = ShopItem::whereKey($row->shop_item_id)->value('effect_value');
        $traits = $row->pet_traits ?? [];
        $entries = [['user_id' => $row->user_id, 'kind' => 'species', 'key' => (string) $species, 'created_at' => now()]];

        foreach (['model', 'backdrop', 'accessory'] as $kind) {
            if (! empty($traits[$kind])) {
                $entries[] = ['user_id' => $row->user_id, 'kind' => $kind, 'key' => $traits[$kind], 'created_at' => now()];
            }
        }

        DB::table('pet_dex')->insertOrIgnore($entries);
    }

    public function dex(User $user): array
    {
        $have = DB::table('pet_dex')->where('user_id', $user->id)->get(['kind', 'key'])
            ->groupBy('kind')->map(fn ($g) => $g->pluck('key')->all());
        $claimed = DB::table('pet_dex_rewards')->where('user_id', $user->id)->pluck('milestone')->all();
        $catalog = $this->catalog();

        $sections = [];
        foreach ($catalog as $kind => $entries) {
            $owned = $have[$kind] ?? [];
            $sections[$kind] = [
                'total' => count($entries),
                'owned' => count(array_intersect(array_column($entries, 'key'), $owned)),
                'entries' => array_map(fn ($e) => $e + ['owned' => in_array($e['key'], $owned, true)], $entries),
            ];
        }

        $milestones = [];
        foreach (self::MILESTONES as $key => $m) {
            $need = $m['need'] === 'all' ? $sections[$m['kind']]['total'] : $m['need'];
            $progress = $sections[$m['kind']]['owned'];
            $milestones[] = [
                'key' => $key,
                'title' => $m['title'],
                'coins' => $m['coins'],
                'keys' => $m['keys'] ?? [],
                'need' => $need,
                'progress' => min($progress, $need),
                'done' => $progress >= $need,
                'claimed' => in_array($key, $claimed, true),
            ];
        }

        return ['sections' => $sections, 'milestones' => $milestones];
    }

    public function claimMilestone(User $user, string $key): array
    {
        $milestone = collect($this->dex($user)['milestones'])->firstWhere('key', $key);

        if (! $milestone) {
            throw new RuntimeException('Такой награды нет.');
        }
        if ($milestone['claimed']) {
            throw new RuntimeException('Награда уже получена.');
        }
        if (! $milestone['done']) {
            throw new RuntimeException('Коллекция ещё не собрана.');
        }

        DB::transaction(function () use ($user, $key, $milestone) {
            $inserted = DB::table('pet_dex_rewards')->insertOrIgnore(['user_id' => $user->id, 'milestone' => $key, 'created_at' => now()]);
            if (! $inserted) {
                throw new RuntimeException('Награда уже получена.');
            }

            CoinService::credit($user, $milestone['coins'], CoinTransaction::SOURCE_OTHER, "Питомцедекс: {$milestone['title']}", "pet-dex:{$user->id}:{$key}");
            foreach ($milestone['keys'] as $slug => $count) {
                LootCaseService::grantKeys($user, $slug, $count);
            }
        });

        return $this->dex($user) + ['balance' => (int) $user->fresh()->apex_coins];
    }

    /** Все виды и черты, что вообще бывают. */
    private function catalog(): array
    {
        $species = ShopItem::where('type', ShopItem::TYPE_PROFILE_PET)->orderBy('sort_order')->get(['effect_value', 'rarity'])
            ->map(fn ($item) => ['key' => $item->effect_value, 'name' => PetNames::species($item->effect_value), 'rarity' => $item->rarity])
            ->values()->all();

        $result = ['species' => $species];
        foreach (PetTraits::TRAITS as $kind => $variants) {
            $total = array_sum(array_column($variants, 1));
            $result[$kind] = collect($variants)
                ->map(fn ($v, $key) => ['key' => $key, 'name' => $v[0], 'chance' => round($v[1] / $total * 100, 2)])
                ->sortByDesc('chance')->values()->all();
        }

        return $result;
    }

    // ---- Топ ----

    /** by: level | rating | rare. */
    public function top(string $by): array
    {
        if ($by === 'rating') {
            return $this->arena->leaderboard();
        }

        $rows = UserInventory::with(['item', 'user:id,username,avatar'])
            ->whereNotNull('pet_traits')
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
            ->get();

        $xp = PetService::xpTotals($rows->pluck('id')->all());

        $cards = $rows->map(function ($row) use ($xp) {
            $card = $this->arena->card($row, PetService::levelOf($xp[$row->id] ?? 0));
            $t = $card['traits'];
            // Общий шанс экземпляра — чем меньше, тем реже
            $card['chance'] = round($t['model']['chance'] * $t['backdrop']['chance'] * $t['accessory']['chance'] / 10000, 5);
            $card['xp'] = $xp[$row->id] ?? 0;

            return $card;
        });

        $sorted = $by === 'rare'
            ? $cards->sortBy('chance')
            : $cards->sortByDesc('xp');

        return $sorted->take(20)->values()->all();
    }

    private function pets(User $user)
    {
        return UserInventory::with(['item', 'user:id,username,avatar'])
            ->where('user_id', $user->id)
            ->whereNotNull('pet_traits')
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
            ->get();
    }
}
