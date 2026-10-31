<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Exceptions\NotEnoughCoins;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Подземелья: поход отрядом питомцев по залам с мобами и боссами.
 *
 * Этаж — это несколько обычных мобов, а закрывает этаж босс. Со
 * следующего этажа мобы сильнее, босс другой.
 *
 * Бой пошаговый (DungeonBattle): каждый раунд игрок выбирает приём
 * каждому живому питомцу, потом ходят все по скорости. Здоровье у
 * питомцев своё и переходит из зала в зал — потрёпанный отряд рискует
 * не дойти до босса, и это чувствуется.
 *
 * Состояние похода — одна json-колонка: этаж, зал, характеристики
 * участников, состояние боя, заранее выбранный противник и добыча.
 */
final class DungeonService
{
    private const RARITY = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3, 'mythic' => 4];

    public function __construct(
        private readonly PetGearService $gear,
        private readonly DungeonBattle $battle,
    ) {
    }

    /* ==================== Походы на сегодня ==================== */

    /** Сколько походов уже начато сегодня. */
    public function runsToday(User $user): int
    {
        return DB::table('dungeon_runs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', today())
            ->count();
    }

    /** Сколько походов докуплено на сегодня. */
    public function extraBought(User $user): int
    {
        return (int) DB::table('dungeon_extra_runs')
            ->where('user_id', $user->id)
            ->where('day', today()->toDateString())
            ->value('bought');
    }

    /** Остаток походов: бесплатные плюс купленные минус начатые. */
    public function runsLeft(User $user): int
    {
        return max(0, Dungeons::DAILY_RUNS + $this->extraBought($user) - $this->runsToday($user));
    }

    /** Цена следующего похода: 800, 1200, 1600… */
    public function nextRunPrice(User $user): int
    {
        return Dungeons::EXTRA_RUN_BASE + $this->extraBought($user) * Dungeons::EXTRA_RUN_STEP;
    }

    /** Купить ещё один поход на сегодня. */
    public function buyExtraRun(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $price = $this->nextRunPrice($user);

            if (! CoinService::canAfford($user->fresh(), $price)) {
                throw new NotEnoughCoins($price - (int) $user->fresh()->apex_coins);
            }

            CoinService::debit($user, $price, CoinTransaction::SOURCE_OTHER, 'Подземелье: дополнительный поход');

            DB::table('dungeon_extra_runs')->upsert(
                [['user_id' => $user->id, 'day' => today()->toDateString(), 'bought' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['user_id', 'day'],
                ['bought' => DB::raw('bought + 1'), 'updated_at' => now()],
            );

            return [
                'runs_left' => $this->runsLeft($user),
                'next_price' => $this->nextRunPrice($user),
                'balance' => (int) $user->fresh()->apex_coins,
            ];
        });
    }

    /* ==================== Обзор ==================== */

    public function overview(User $user): array
    {
        $active = DB::table('dungeon_runs')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        /*
         * Последний завершённый поход за сегодня. Нужен окну итогов как
         * страховка: если ответ на ход не дошёл (перезагрузка, старый
         * чанк), интерфейс всё равно покажет, чем всё кончилось.
         */
        $lastFinished = DB::table('dungeon_runs')
            ->where('user_id', $user->id)
            ->whereIn('status', ['cleared', 'wiped', 'retreat'])
            ->where('updated_at', '>=', today())
            ->orderByDesc('updated_at')
            ->first();

        $pets = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->whereNotNull('pet_traits')
            ->get();

        $levels = PetService::levels($pets->pluck('id')->all());
        $arena = app(PetArenaService::class);

        $squad = $pets->map(fn (UserInventory $pet) => [
            ...$arena->card($pet, $levels[$pet->id] ?? 1),
            'gear' => $this->gear->equipped($pet),
            'bonus' => $this->gear->bonus($pet),
            'busy' => PetLocks::reason($pet->id),
            'hp_max' => $this->petMaxHp($pet, $levels[$pet->id] ?? 1),
            // Условная сила для подбора отряда: атака весит больше защиты
            'power' => $this->petPower($pet, $levels[$pet->id] ?? 1),
            'ability' => \App\Domains\Players\Support\DungeonBattle::ABILITIES[$arena->card($pet, 1)['element']] ?? null,
        ])->values()->all();

        return [
            'runs_left' => $this->runsLeft($user),
            'runs_per_day' => Dungeons::DAILY_RUNS,
            'extra_bought' => $this->extraBought($user),
            'next_run_price' => $this->nextRunPrice($user),
            'squad_min' => Dungeons::SQUAD_MIN,
            'squad_max' => Dungeons::SQUAD_MAX,
            'dungeons' => array_map(fn (array $d) => $d + ['available' => $this->canEnter($d, $squad)], Dungeons::all()),
            'features' => Dungeons::TRAITS,
            'reference' => $this->battle->reference(),
            'pets' => $squad,
            'run' => $active ? $this->present($active) : null,
            'last_result' => $lastFinished ? $this->present($lastFinished) : null,
            'gear_bag' => $this->gear->bag($user),
            'gear_slots' => Dungeons::SLOT_NAMES,
        ];
    }

    private function canEnter(array $dungeon, array $squad): bool
    {
        $ready = array_filter($squad, fn (array $p) => $p['busy'] === null && $p['level'] >= $dungeon['min_level']);

        return count($ready) >= Dungeons::SQUAD_MIN;
    }

    /* ==================== Поход ==================== */

    public function start(User $user, string $dungeonKey, array $squadIds): array
    {
        $dungeon = Dungeons::dungeon($dungeonKey);

        if (! $dungeon) {
            throw new RuntimeException('Такого подземелья нет.');
        }

        $squadIds = array_values(array_unique(array_map('intval', $squadIds)));

        if (count($squadIds) < Dungeons::SQUAD_MIN) {
            throw new RuntimeException('В отряд нужно взять минимум ' . Dungeons::SQUAD_MIN . ' питомца.');
        }

        if (count($squadIds) > Dungeons::SQUAD_MAX) {
            throw new RuntimeException('В отряде не больше ' . Dungeons::SQUAD_MAX . ' питомцев.');
        }

        if (DB::table('dungeon_runs')->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw new RuntimeException('Сначала закончи текущий поход.');
        }

        if ($this->runsLeft($user) <= 0) {
            throw new RuntimeException('Походы на сегодня закончились. Купи ещё поход или возвращайся завтра.');
        }

        $members = $this->squadMembers($user, $squadIds);
        $missing = array_diff($squadIds, array_column($members, 'inventory_id'));

        if ($missing) {
            throw new RuntimeException('Питомец занят или не твой — проверь отряд.');
        }

        foreach ($members as $member) {
            if ($member['level'] < $dungeon['min_level']) {
                throw new RuntimeException("«{$member['name']}» не дорос: нужно {$dungeon['min_level']} уровня.");
            }

            if ($member['busy']) {
                throw new RuntimeException("«{$member['name']}» занят: {$member['busy']}");
            }
        }

        $state = [
            'floor' => 1,
            'encounter' => 0,
            'phase' => 'idle',
            'members' => $members,
            'pending' => $this->rollEnemy($dungeonKey, 1, 0),
            'battle' => null,
            'log' => [['kind' => 'start', 'text' => "Отряд входит в «{$dungeon['name']}». Готовьтесь к первому залу."]],
            'loot' => ['coins' => 0, 'gear' => [], 'items' => [], 'keys' => []],
        ];

        $id = DB::table('dungeon_runs')->insertGetId([
            'user_id' => $user->id,
            'dungeon' => $dungeonKey,
            'status' => 'active',
            'squad' => json_encode($squadIds),
            'state' => json_encode($state),
            'coins' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->present(DB::table('dungeon_runs')->find($id));
    }

    /** Войти в зал: начать бой с показанным противником. */
    public function enterRoom(User $user, int $runId): array
    {
        $run = $this->ownRun($user, $runId);

        if ($run->status !== 'active') {
            throw new RuntimeException('Поход уже закончен.');
        }

        $state = json_decode($run->state, true);

        if (($state['phase'] ?? 'idle') === 'battle') {
            throw new RuntimeException('Бой уже идёт.');
        }

        if (empty($state['pending'])) {
            throw new RuntimeException('Противника нет — зал уже пройден.');
        }

        $state['battle'] = $this->battle->begin($state['members'], $state['pending']);
        $state['phase'] = 'battle';

        return $this->save($run->id, 'active', $state);
    }

    /** Сделать ход: приёмы каждому живому питомцу. */
    public function move(User $user, int $runId, array $moves): array
    {
        $run = $this->ownRun($user, $runId);

        if ($run->status !== 'active') {
            throw new RuntimeException('Поход уже закончен.');
        }

        $state = json_decode($run->state, true);

        if (($state['phase'] ?? 'idle') !== 'battle' || empty($state['battle'])) {
            throw new RuntimeException('Бой ещё не начат.');
        }

        $state['battle'] = $this->battle->resolveRound($state['battle'], $moves);

        if ($state['battle']['status'] === 'lost') {
            $state['phase'] = 'done';
            $state['pending'] = null;

            $penalty = $this->applyWipePenalty($user, $run, $state);

            $state['log'][] = [
                'kind' => 'wipe',
                'text' => $penalty > 0
                    ? "Отряд пал. Поход окончен, штраф {$penalty} ₳."
                    : 'Отряд пал. Поход окончен.',
            ];

            return $this->save($run->id, 'wiped', $state);
        }

        if ($state['battle']['status'] === 'won') {
            return $this->winRoom($user, $run, $state);
        }

        return $this->save($run->id, 'active', $state);
    }

    /** Зал пройден: добыча, перенос здоровья, следующий этаж или зал. */
    private function winRoom(User $user, object $run, array $state): array
    {
        $dungeon = Dungeons::dungeon($run->dungeon);
        $floor = (int) $state['floor'];
        $encounter = (int) $state['encounter'];
        $foe = $state['battle']['foe'];

        // Здоровье из боя переносим в отряд: до следующего зала доходит не всё
        foreach ($state['battle']['units'] as $unit) {
            foreach ($state['members'] as $index => $member) {
                if ((int) $member['inventory_id'] === (int) $unit['id']) {
                    $state['members'][$index]['hp'] = (int) $unit['hp'];
                }
            }
        }

        $state = $this->grantLoot($user, $run, $dungeon, $floor, (bool) $foe['is_boss'], $state);

        $isBoss = (bool) $foe['is_boss'];
        $nextEncounter = $encounter + 1;
        $nextFloor = $floor;

        if ($isBoss) {
            $nextFloor = $floor + 1;
            $nextEncounter = 0;
        }

        $floors = (int) $dungeon['floors'];
        $cleared = $nextFloor > $floors;

        $state['battle'] = null;
        $state['phase'] = $cleared ? 'done' : 'idle';

        if ($cleared) {
            $state['pending'] = null;
            $state['log'][] = ['kind' => 'clear', 'text' => 'Все этажи пройдены. Подземелье зачищено.'];

            return $this->save($run->id, 'cleared', $state);
        }

        if ($isBoss) {
            // После босса отряд переводит дух: лечим долю максимума
            foreach ($state['members'] as $index => $member) {
                $rest = (int) round($member['max'] * Dungeons::REST_SHARE);
                $state['members'][$index]['hp'] = min((int) $member['max'], (int) $member['hp'] + $rest);
            }

            $state['log'][] = ['kind' => 'rest', 'text' => 'Привал после босса: отряд перевёл дух.'];
        }

        $state['floor'] = $nextFloor;
        $state['encounter'] = $nextEncounter;
        $state['pending'] = $this->rollEnemy((string) $run->dungeon, $nextFloor, $nextEncounter);

        return $this->save($run->id, 'active', $state);
    }

    /**
     * Поражение стоит монет: списываем штраф по ступени, но не больше
     * остатка. Отступление не штрафуется.
     *
     * @return int сколько списали
     */
    private function applyWipePenalty(User $user, object $run, array &$state): int
    {
        $amount = Dungeons::wipePenalty((string) $run->dungeon);

        $paid = $amount > 0
            ? CoinService::penalty(
                $user,
                $amount,
                'dungeon_wipe',
                "Поражение в подземелье «{$run->dungeon}»",
                ['reference_type' => 'dungeon_run', 'reference_id' => (int) $run->id],
            )
            : 0;

        $state['loot']['penalty'] = $paid;
        $state['loot']['penalty_full'] = $amount;

        return $paid;
    }

    /** Отступить: поход закрывается, добыча остаётся у игрока. */
    public function retreat(User $user, int $runId): array
    {
        $run = $this->ownRun($user, $runId);

        if ($run->status !== 'active') {
            throw new RuntimeException('Поход уже закончен.');
        }

        $state = json_decode($run->state, true);
        $state['log'][] = ['kind' => 'retreat', 'text' => 'Отряд отступил. Найденное осталось при вас.'];
        $state['battle'] = null;
        $state['phase'] = 'done';
        $state['pending'] = null;

        return $this->save($run->id, 'retreat', $state);
    }

    private function save(int $runId, string $status, array $state): array
    {
        $state['log'] = array_slice($state['log'], -40);

        DB::table('dungeon_runs')->where('id', $runId)->update([
            'status' => $status,
            'state' => json_encode($state),
            'coins' => (int) ($state['loot']['coins'] ?? 0),
            'updated_at' => now(),
        ]);

        return $this->present(DB::table('dungeon_runs')->find($runId));
    }

    /* ==================== Противники ==================== */

    private function rollEnemy(string $key, int $floor, int $encounter): array
    {
        $dungeon = Dungeons::dungeon($key) ?? Dungeons::DUNGEONS['catacombs'];
        $isBoss = Dungeons::isBossRoom($dungeon, $floor, $encounter);
        $base = $isBoss ? Dungeons::boss($key, $floor) : Dungeons::mob($key);

        $k = 1 + 0.18 * ($floor - 1);
        $trait = Dungeons::trait((string) $base['trait']);

        return [
            'name' => $base['name'],
            'sprite' => $base['sprite'],
            'element' => $base['element'] ?? 'beast',
            'trait' => $base['trait'],
            'trait_name' => $trait['name'],
            'trait_text' => $trait['text'],
            'is_boss' => $isBoss,
            'hp' => (int) round($base['hp'] * $k),
            'atk' => (int) round($base['atk'] * $k),
            'def' => (int) round($base['def'] * $k),
            'spd' => (int) round(10 + ($isBoss ? 6 : 0) + 2 * $floor),
        ];
    }

    /* ==================== Добыча ==================== */

    private function grantLoot(User $user, object $run, array $dungeon, int $floor, bool $isBoss, array $state): array
    {
        $stage = (int) $dungeon['stage'];
        $drops = Dungeons::DROPS[$stage];

        $coins = $isBoss
            ? (int) Dungeons::COINS_PER_BOSS[$stage] * $floor
            : (int) Dungeons::COINS_PER_MOB[$stage] * $floor;

        $coins = (int) round($coins * (100 + random_int(-10, 10)) / 100);

        $key = "dungeon:{$run->id}:{$floor}:{$state['encounter']}";

        if ($coins > 0) {
            CoinService::credit($user, $coins, CoinTransaction::SOURCE_OTHER, 'Подземелье: добыча', $key);
            $state['loot']['coins'] += $coins;
        }

        $xp = $isBoss ? Dungeons::XP_PER_BOSS : Dungeons::XP_PER_MOB;

        foreach ($state['members'] as $member) {
            DB::table('user_inventory')->where('id', $member['inventory_id'])->increment('pet_xp', $xp);
        }

        $gearChance = $isBoss ? 100 : (int) $drops['gear'];

        if (random_int(1, 100) <= $gearChance) {
            $slug = Dungeons::rollGear($stage, $isBoss);
            $rarity = Dungeons::rollRarity($stage, $isBoss);
            $id = $this->gear->grant($user, $slug, $rarity);

            if ($id !== null) {
                $gear = Dungeons::GEAR[$slug] ?? [];
                $stats = Dungeons::scaledStats($gear, $rarity);

                $state['loot']['gear'][] = $id;
                // Для окна итогов: id мало, нужны название, спрайт и слот
                $state['loot']['items'][] = [
                    'inventory_id' => $id,
                    'slug' => $slug,
                    'name' => $gear['name'] ?? $slug,
                    'sprite' => $gear['sprite'] ?? null,
                    'slot' => $gear['slot'] ?? null,
                    'slot_name' => Dungeons::slotName((string) ($gear['slot'] ?? '')),
                    'rarity' => $rarity,
                    'rarity_name' => Dungeons::rarityName($rarity),
                    ...$stats,
                ];

                $state['log'][] = [
                    'kind' => 'gear',
                    'text' => 'Найдено: ' . ($gear['name'] ?? $slug) . '.',
                    'sprite' => $gear['sprite'] ?? null,
                ];
            }
        }

        foreach ([['key', $drops['key_slug']], ['key_pet', $drops['key_pet_slug']]] as [$field, $slug]) {
            if (random_int(1, 10000) <= (int) round($drops[$field] * 100)) {
                LootCaseService::grantKeys($user, $slug, 1);
                $state['loot']['keys'][] = $slug;
                $state['log'][] = ['kind' => 'key', 'text' => 'Найден ключ от кейса.'];
            }
        }

        return $state;
    }

    /* ==================== Вспомогательное ==================== */

    private function squadMembers(User $user, array $ids): array
    {
        $pets = UserInventory::with('item')->where('user_id', $user->id)->whereIn('id', $ids)->get();
        $levels = PetService::levels($ids);
        $arena = app(PetArenaService::class);

        $members = [];

        foreach ($pets as $pet) {
            if ($pet->pet_traits === null) {
                continue;
            }

            $level = $levels[$pet->id] ?? 1;
            $r = self::RARITY[$pet->pet_traits['rarity'] ?? 'common'] ?? 0;
            $bonus = $this->gear->bonus($pet);
            $max = (int) round((60 + $level * 6 + $r * 8 + $bonus['hp']) * DungeonBattle::HP_FACTOR);
            $species = $pet->item?->effect_value;

            $members[] = [
                'inventory_id' => $pet->id,
                'name' => $pet->pet_name ?: ($pet->item?->name ?? 'Питомец'),
                'species' => $species,
                'traits' => PetTraits::present($pet->pet_traits, $pet->pet_serial),
                // Что надето: показываем на сцене боя
                'gear' => array_values($this->gear->equipped($pet)),
                'element' => $arena->card($pet, $level)['element'],
                'level' => $level,
                'hp' => $max,
                'max' => $max,
                'atk' => (int) round(10 + $level * 1.2 + $r * 1.5) + $bonus['atk'],
                'def' => (int) round(5 + $level * 0.6 + $r) + $bonus['def'],
                'spd' => (int) round(10 + $level * 0.4) + $bonus['spd'],
                'busy' => PetLocks::reason($pet->id),
            ];
        }

        return $members;
    }

    /**
     * Условная сила питомца для подбора отряда.
     *
     * Не боевой показатель, а вес для сортировки: атака важнее защиты,
     * здоровье входит вчетверо слабее. Связка добавляется отдельно, в
     * интерфейсе.
     */
    private function petPower(UserInventory $pet, int $level): int
    {
        $r = self::RARITY[$pet->pet_traits['rarity'] ?? 'common'] ?? 0;
        $bonus = $this->gear->bonus($pet);

        $atk = (int) round(10 + $level * 1.2 + $r * 1.5) + $bonus['atk'];
        $def = (int) round(5 + $level * 0.6 + $r) + $bonus['def'];
        $spd = (int) round(10 + $level * 0.4) + $bonus['spd'];
        $hp = $this->petMaxHp($pet, $level);

        return (int) round($atk * 2 + $def * 1.5 + $spd + $hp / 4);
    }

    private function petMaxHp(UserInventory $pet, int $level): int
    {
        $r = self::RARITY[$pet->pet_traits['rarity'] ?? 'common'] ?? 0;
        $bonus = $this->gear->bonus($pet);

        return (int) round((60 + $level * 6 + $r * 8 + $bonus['hp']) * DungeonBattle::HP_FACTOR);
    }

    private function ownRun(User $user, int $id): object
    {
        $run = DB::table('dungeon_runs')->find($id);

        if (! $run || (int) $run->user_id !== (int) $user->id) {
            throw new RuntimeException('Поход не найден.');
        }

        return $run;
    }

    public function present(object $run): array
    {
        $dungeon = Dungeons::dungeon($run->dungeon);
        $state = json_decode($run->state, true);

        $floor = (int) $state['floor'];
        $encounter = (int) $state['encounter'];
        $perFloor = (int) ($dungeon['per_floor'] ?? 1);
        $battle = $state['battle'] ?? null;

        return [
            'id' => (int) $run->id,
            'dungeon' => $run->dungeon,
            'name' => $dungeon['name'] ?? $run->dungeon,
            'about' => $dungeon['about'] ?? '',
            'emblem' => $dungeon['emblem'] ?? 'mob-rat',
            'tone' => $dungeon['tone'] ?? 'moss',
            'status' => $run->status,
            'phase' => $state['phase'] ?? 'idle',
            'floor' => $floor,
            'floors' => (int) ($dungeon['floors'] ?? 1),
            'encounter' => $encounter,
            'per_floor' => $perFloor,
            'rooms' => Dungeons::rooms($dungeon),
            'room' => ($floor - 1) * ($perFloor + 1) + $encounter + 1,
            'is_boss_next' => Dungeons::isBossRoom($dungeon, $floor, $encounter),
            'members' => $state['members'],
            'pending' => $state['pending'] ?? null,
            'battle' => $battle,
            'log' => array_slice($state['log'], -12),
            'loot' => $state['loot'],
        ];
    }
}
