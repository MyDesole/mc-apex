<?php

namespace App\Domains\Games\Services;

use App\Domains\Games\Support\MinerWorld;
use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Мини-игра «Шахтёр» — фарм ApexCoin без дневного лимита, с перерывом
 * между забегами.
 *
 * Сетка 8×7: верхний ряд открыт, копать можно только клетку рядом с уже
 * открытой — вглубь шахты. Чем глубже, тем богаче руда; докопался до
 * дна — спускаешься на ярус ниже (Пещеры → Сланец → Незер → Энд).
 * Руда подряд копит комбо, TNT и динамит взрывают соседей, лава и гравий
 * отнимают удары, в сундуке бывает мимик, житель продаёт помощь за долю
 * добычи. Где что лежит, знает только сервер: каждый удар — запрос,
 * клиент видит блок после удара (и подсказки фонаря).
 */
class MinerService
{
    public const COLS = 8;
    public const ROWS = 7;
    public const ENERGY = 24;
    public const COOLDOWN_SECONDS = 600;
    /** Вагонетка: минус секунд перерыва за уровень. */
    public const CART_STEP = 15;
    /** Удары на спуск по лестнице и сколько добавляет уровень кирки. */
    public const DESCEND_COST = 4;
    public const PICKAXE_STEP = 1;

    public const VALUES = MinerWorld::VALUES;

    /** Удары: лава обжигает, гравий осыпается, незеритовая кирка ломает бедрок. */
    public const LAVA_BURN = 2;
    public const GRAVEL_COLLAPSE = 1;
    public const WALL_BREAK = 3;
    /** Шанс (%), что гравий откроет пещеру, а не осыплется. */
    public const CAVE_CHANCE = 35;
    public const MIMIC_CHANCE = 35;
    /** Мимик откусывает долю добычи. */
    public const MIMIC_BITE = 0.3;

    public const COMBO_STEP = 0.05;
    public const COMBO_MAX = 1.5;
    /** Удача за уровень: вес редкой руды и шанс (%) двойного дропа. */
    public const FORTUNE_WEIGHT = 0.04;
    public const FORTUNE_DOUBLE = 1;

    /** Сундук: монеты × множитель яруса, шансы (%) на артефакт и динамит. */
    public const CHEST_LOOT = [30, 80];
    public const CHEST_ARTIFACT = 15;
    public const CHEST_DYNAMITE = 25;
    public const TIER_LOOT = [1 => 1.0, 2 => 1.6, 3 => 2.4, 4 => 3.2];
    /** Повторный артефакт продаётся: монеты × ярус. */
    public const DUPLICATE_ARTIFACT = 40;

    public function __construct(private readonly MinerProgress $progress)
    {
    }

    public function state(User $user): array
    {
        $run = $this->active($user);
        $profile = $this->progress->profile($user);
        $last = DB::table('miner_runs')->where('user_id', $user->id)->latest('id')->first();

        // Только закрытые забеги: монеты идущего ещё не на счёте.
        // Псевдонимы не совпадают с ключевыми словами MySQL.
        $stats = DB::table('miner_runs')
            ->where('user_id', $user->id)
            ->where('status', 'finished')
            ->selectRaw('MAX(coins) AS best_coins, SUM(coins) AS total_coins, COUNT(*) AS run_count')
            ->first();

        return [
            'run' => $run ? $this->present($run) : null,
            'cooldown_until' => $run ? null : $this->cooldownUntil($last, $profile),
            'cooldown' => $this->cooldownSeconds($profile),
            'best' => (int) $stats->best_coins,
            'total' => (int) $stats->total_coins,
            'runs' => (int) $stats->run_count,
            'values' => self::VALUES,
            'energy' => $this->energyFor($profile),
            'cols' => self::COLS,
            'rows' => self::ROWS,
            'tiers' => array_map(fn ($t) => $t['name'], MinerWorld::TIERS),
            'upgrades' => $this->progress->upgrades($profile),
            'artifacts' => $this->progress->artifacts($profile),
            'pet_bonus' => PetPerks::minerBonus($this->petLevel($user)),
            'quests' => $this->progress->quests($user, $profile),
            'trades' => array_map(fn ($t) => $t['text'], MinerWorld::TRADES),
            'rush' => MinerProgress::rush(),
            'top' => $this->progress->leaderboard($user),
        ];
    }

    /** Для панели на главной: только идёт ли забег и когда остынет кирка. */
    public function brief(User $user): array
    {
        $run = $this->active($user);
        $last = DB::table('miner_runs')->where('user_id', $user->id)->latest('id')->first();

        return [
            'active' => (bool) $run,
            'cooldown_until' => $run ? null : $this->cooldownUntil($last, $this->progress->profile($user)),
            'best' => (int) DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'finished')->max('coins'),
        ];
    }

    public function start(User $user): array
    {
        if ($this->active($user)) {
            throw new RuntimeException('Забег уже идёт.');
        }

        $profile = $this->progress->profile($user);
        $last = DB::table('miner_runs')->where('user_id', $user->id)->latest('id')->first();

        if ($until = $this->cooldownUntil($last, $profile)) {
            $seconds = now()->diffInSeconds(Carbon::parse($until));
            throw new RuntimeException('Кирка остывает — новый забег через ' . max(1, (int) ceil($seconds)) . ' с.');
        }

        $energy = $this->energyFor($profile);
        $meta = [
            'tier' => 1,
            'combo' => 0,
            'best_combo' => 0,
            'max_energy' => $energy,
            'dynamite' => (int) $profile->dynamite,
            'pickaxe' => (int) $profile->pickaxe,
            'fortune' => (int) $profile->fortune,
            'lantern' => (int) $profile->lantern,
            'rush' => MinerProgress::rush()['active'],
        ] + $this->freshTier();

        $grid = $this->generate(1, $meta);
        // Верхний ряд — открытая поверхность, копать начинаем под ним
        $opened = range(0, self::COLS - 1);

        $id = DB::table('miner_runs')->insertGetId([
            'user_id' => $user->id,
            'grid' => json_encode($grid),
            'opened' => json_encode($opened),
            'meta' => json_encode($meta),
            'energy' => $energy,
            'coins' => array_sum(array_map(fn ($i) => self::VALUES[$grid[$i]], $opened)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->state($user) + ['started' => $id];
    }

    /** Удар киркой по клетке. */
    public function dig(User $user, int $cell): array
    {
        return $this->act($user, function (array &$s) use ($cell) {
            $this->assertTarget($s, $cell);
            $block = $s['grid'][$cell];
            // Фонарь светит вокруг последнего удара
            $s['meta']['last'] = $cell;

            if (in_array($block, MinerWorld::WALLS, true)) {
                // Первый удар только показывает бедрок — удар не тратится.
                // Незеритовая кирка раскалывает его вторым, осознанным ударом.
                if ($s['meta']['pickaxe'] < 5 || ! in_array($cell, $s['meta']['seen'], true)) {
                    $s['meta']['seen'][] = $cell;
                    $s['events'][] = ['type' => 'wall', 'cell' => $cell];

                    return;
                }

                $this->spend($s, self::WALL_BREAK);
                $s['opened'][] = $cell;
                $s['events'][] = ['type' => 'wall_break', 'cell' => $cell];

                return;
            }

            $this->spend($s, 1);

            if (MinerWorld::isOre($block)) {
                $s['meta']['combo']++;
                $s['meta']['best_combo'] = max($s['meta']['best_combo'], $s['meta']['combo']);
                $s['track']['combo'] = $s['meta']['combo'];

                if ($s['meta']['combo'] >= 2) {
                    $s['events'][] = ['type' => 'combo', 'cell' => $cell, 'combo' => $s['meta']['combo'], 'mult' => $this->comboMult($s)];
                }
            } elseif (in_array($block, MinerWorld::ROCK, true) || $block === 'lava') {
                $this->breakCombo($s);
            }

            if ($block === 'lava') {
                $this->spend($s, self::LAVA_BURN, true);
                $s['events'][] = ['type' => 'lava', 'cell' => $cell, 'lost' => self::LAVA_BURN];
            }

            if ($block === 'gravel') {
                if (random_int(1, 100) <= self::CAVE_CHANCE) {
                    $this->reveal($s, $cell);
                    $s['events'][] = ['type' => 'cave', 'cell' => $cell];

                    foreach ($this->neighbours($cell) as $n) {
                        if ($this->closedFloor($s, $n)) {
                            $this->reveal($s, $n);
                        }
                    }

                    return;
                }

                $this->spend($s, self::GRAVEL_COLLAPSE, true);
                $this->breakCombo($s);
                $s['events'][] = ['type' => 'collapse', 'cell' => $cell, 'lost' => self::GRAVEL_COLLAPSE];
            }

            $this->reveal($s, $cell);
        });
    }

    /** Динамит из запаса: направленный взрыв крестом, без удара кирки. */
    public function blast(User $user, int $cell): array
    {
        return $this->act($user, function (array &$s) use ($cell) {
            if ($s['meta']['dynamite'] <= 0) {
                throw new RuntimeException('Динамит кончился.');
            }

            $this->assertTarget($s, $cell);
            $s['meta']['dynamite']--;
            $s['meta']['last'] = $cell;

            if (! in_array($s['grid'][$cell], MinerWorld::WALLS, true)) {
                $this->reveal($s, $cell);
            }

            $this->explode($s, $cell, false);
        });
    }

    /** Открыть выкопанный сундук: клад или мимик. */
    public function openChest(User $user, int $cell): array
    {
        return $this->act($user, function (array &$s) use ($cell) {
            if (($s['meta']['chests'][$cell] ?? null) !== 'closed') {
                throw new RuntimeException('Здесь нет закрытого сундука.');
            }

            $s['track']['chests'] = ($s['track']['chests'] ?? 0) + 1;

            if ($s['grid'][$cell] === 'mimic') {
                $bite = (int) floor($s['coins'] * self::MIMIC_BITE);
                $s['coins'] -= $bite;
                $s['meta']['chests'][$cell] = 'mimic';
                $this->breakCombo($s);
                $s['events'][] = ['type' => 'mimic', 'cell' => $cell, 'lost' => $bite];

                return;
            }

            $s['meta']['chests'][$cell] = 'open';
            $loot = (int) round(random_int(...self::CHEST_LOOT) * self::TIER_LOOT[$s['meta']['tier']]);
            $s['gain'] += $loot;
            $s['coins'] += $loot;
            $s['events'][] = ['type' => 'loot', 'cell' => $cell, 'coins' => $loot];

            if (random_int(1, 100) <= self::CHEST_DYNAMITE) {
                $s['meta']['dynamite']++;
                $s['events'][] = ['type' => 'loot_dynamite', 'cell' => $cell];
            }

            if (random_int(1, 100) <= self::CHEST_ARTIFACT) {
                $this->findArtifact($s, $cell);
            }
        });
    }

    /** Сделка с жителем — одна на жителя, платишь долей добычи забега. */
    public function trade(User $user, int $cell, string $offer): array
    {
        return $this->act($user, function (array &$s) use ($cell, $offer) {
            if (($s['meta']['villagers'][$cell] ?? null) !== 'open') {
                throw new RuntimeException('Житель уже ушёл.');
            }

            if (! isset(MinerWorld::TRADES[$offer])) {
                throw new RuntimeException('Такой сделки нет.');
            }

            $cost = $this->tradeCost($offer, $s['coins']);
            if ($s['coins'] < $cost) {
                throw new RuntimeException("Житель просит {$cost} монет добычи — пока не хватает.");
            }

            $s['coins'] -= $cost;
            $s['meta']['villagers'][$cell] = 'used';
            $s['track']['trade'] = 1;

            match ($offer) {
                'strikes' => $s['energy'] += 5,
                'dynamite' => $s['meta']['dynamite']++,
                'map' => $s['meta']['map'] = true,
            };

            $s['meta']['max_energy'] = max($s['meta']['max_energy'], $s['energy']);
            $s['events'][] = ['type' => 'trade', 'cell' => $cell, 'offer' => $offer, 'cost' => $cost];
        });
    }

    /** Спуститься на ярус ниже: нужно докопаться до нижнего ряда. */
    public function descend(User $user): array
    {
        return $this->act($user, function (array &$s) {
            if (! $this->canDescend($s)) {
                throw new RuntimeException($s['meta']['tier'] >= count(MinerWorld::TIERS)
                    ? 'Глубже Энда ничего нет.'
                    : 'Сначала докопайся до дна яруса.');
            }

            if ($s['energy'] < self::DESCEND_COST) {
                throw new RuntimeException('На спуск по лестнице нужно ' . self::DESCEND_COST . ' удара кирки.');
            }

            $this->spend($s, self::DESCEND_COST);
            $s['meta'] = ['tier' => $s['meta']['tier'] + 1] + $this->freshTier() + $s['meta'];
            $s['grid'] = $this->generate($s['meta']['tier'], $s['meta']);
            $s['opened'] = range(0, self::COLS - 1);
            $s['track']['descend'] = 1;
            $s['track']['nether'] = $s['meta']['tier'] >= 3 ? 1 : 0;
            $s['events'][] = ['type' => 'descend', 'tier' => $s['meta']['tier'], 'name' => MinerWorld::tier($s['meta']['tier'])['name']];
        });
    }

    /** Закончить досрочно и забрать накопанное. */
    public function cashOut(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $run = DB::table('miner_runs')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $run) {
                throw new RuntimeException('Забег не начат.');
            }

            return ['finished' => $this->finishRun($user, $run, (int) $run->coins, $this->meta($run))] + $this->state($user);
        });
    }

    /**
     * Общая обвязка действия: блокируем забег, меняем состояние $s,
     * сохраняем, продвигаем задания и закрываем забег, если ходов нет.
     */
    private function act(User $user, callable $action): array
    {
        return DB::transaction(function () use ($user, $action) {
            $run = DB::table('miner_runs')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $run) {
                throw new RuntimeException('Забег не начат.');
            }

            $s = [
                'user_id' => $user->id,
                'grid' => json_decode($run->grid, true),
                'opened' => json_decode($run->opened, true),
                'meta' => $this->meta($run),
                'energy' => (int) $run->energy,
                'coins' => (int) $run->coins,
                'gain' => 0,
                'found' => [],
                'events' => [],
                'track' => [],
            ];

            $action($s);

            $s['opened'] = array_values(array_unique($s['opened']));
            $s['meta']['seen'] = array_values(array_unique($s['meta']['seen']));

            DB::table('miner_runs')->where('id', $run->id)->update([
                'grid' => json_encode($s['grid']),
                'opened' => json_encode($s['opened']),
                'meta' => json_encode($s['meta']),
                'energy' => max(0, $s['energy']),
                'coins' => max(0, $s['coins']),
                'updated_at' => now(),
            ]);

            $this->progress->track($user->id, $s['track']);

            $result = [
                'found' => $s['found'],
                'gain' => $s['gain'],
                'events' => $s['events'],
            ];

            // Ходов не осталось — забег окончен, монеты на счёт
            if (! $this->canAct($s)) {
                $result['finished'] = $this->finishRun($user, $run, max(0, $s['coins']), $s['meta']);
            }

            return $result + $this->state($user);
        });
    }

    /** Открыть блок и посчитать, что он дал (с комбо и двойным дропом). */
    private function reveal(array &$s, int $cell): void
    {
        if (in_array($cell, $s['opened'], true)) {
            return;
        }

        $s['opened'][] = $cell;
        $block = $s['grid'][$cell];
        $value = self::VALUES[$block];
        $double = false;

        if (MinerWorld::isOre($block)) {
            $double = random_int(1, 100) <= self::FORTUNE_DOUBLE * $s['meta']['fortune'];
            $value = (int) round($value * $this->comboMult($s) * ($double ? 2 : 1));

            if ($block === 'diamond') {
                $s['track']['diamonds'] = ($s['track']['diamonds'] ?? 0) + 1;
            }
        }

        $s['gain'] += $value;
        $s['coins'] += $value;
        $s['found'][] = ['cell' => $cell, 'block' => $block === 'mimic' ? 'chest' : $block, 'value' => $value] + ($double ? ['double' => true] : []);

        match ($block) {
            'chest', 'mimic' => $s['meta']['chests'][$cell] = 'closed',
            'villager' => $s['meta']['villagers'][$cell] = 'open',
            'relic' => $this->findArtifact($s, $cell),
            'tnt' => $this->explode($s, $cell),
            default => null,
        };
    }

    /** Взрыв: соседи по кругу открываются бесплатно, TNT рядом — цепочкой. */
    private function explode(array &$s, int $cell, bool $diagonal = true): void
    {
        $s['track']['blasts'] = ($s['track']['blasts'] ?? 0) + 1;
        $s['events'][] = ['type' => 'blast', 'cell' => $cell];

        foreach ($this->neighbours($cell, $diagonal) as $n) {
            if ($this->closedFloor($s, $n)) {
                $this->reveal($s, $n);
            }
        }
    }

    private function findArtifact(array &$s, int $cell): void
    {
        $tier = $s['meta']['tier'];
        $pool = array_keys(array_filter(MinerWorld::ARTIFACTS, fn ($a) => $a['tier'] <= $tier));
        // Артефакты своего яруса выпадают втрое чаще старых
        $weights = array_combine($pool, array_map(fn ($id) => MinerWorld::ARTIFACTS[$id]['tier'] === $tier ? 3 : 1, $pool));
        $id = $this->roll($weights);

        $isNew = $this->progress->addArtifact($s['user_id'], $id);
        $sold = $isNew ? 0 : self::DUPLICATE_ARTIFACT * $tier;
        $s['gain'] += $sold;
        $s['coins'] += $sold;
        $s['track']['relic'] = 1;
        $s['meta']['artifacts'][] = $id;
        $s['events'][] = ['type' => 'relic', 'cell' => $cell, 'id' => $id, 'name' => MinerWorld::ARTIFACTS[$id]['name'], 'new' => $isNew, 'coins' => $sold];
    }

    private function finishRun(User $user, object $run, int $coins, array $meta): array
    {
        DB::table('miner_runs')->where('id', $run->id)->update([
            'coins' => $coins,
            'status' => 'finished',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        // Перк питомца: лучший из гуляющих по профилю добавляет процент
        $petBonus = (int) floor($coins * PetPerks::minerBonus($this->petLevel($user)));
        // Коллекция артефактов: +1% за каждый разный
        $artifactBonus = (int) floor($coins * $this->progress->artifactBonus($this->progress->profile($user)));
        $total = $coins + $petBonus + $artifactBonus;

        if ($total > 0) {
            CoinService::credit($user, $total, CoinTransaction::SOURCE_OTHER, 'Шахтёр: забег', "miner:{$run->id}");
        }

        $this->progress->track($user->id, ['runs' => 1, 'coins' => $coins]);

        return [
            'coins' => $total,
            'mined' => $coins,
            'pet_bonus' => $petBonus,
            'artifact_bonus' => $artifactBonus,
            'tier' => $meta['tier'],
            'best_combo' => $meta['best_combo'],
            'artifacts' => $meta['artifacts'],
            'balance' => (int) $user->fresh()->apex_coins,
        ];
    }

    /** Можно ли ещё что-то сделать в забеге. */
    private function canAct(array $s): bool
    {
        if (in_array('closed', $s['meta']['chests'], true) || in_array('open', $s['meta']['villagers'], true)) {
            return true;
        }

        $targets = array_filter(array_keys($s['grid']), fn ($i) => $this->reachable($s, $i));

        if ($s['energy'] > 0) {
            if ($this->canDescend($s) && $s['energy'] >= self::DESCEND_COST) {
                return true;
            }

            foreach ($targets as $i) {
                if (! in_array($s['grid'][$i], MinerWorld::WALLS, true) || $s['meta']['pickaxe'] >= 5) {
                    return true;
                }
            }
        }

        return $s['meta']['dynamite'] > 0 && $targets;
    }

    private function canDescend(array $s): bool
    {
        if ($s['meta']['tier'] >= count(MinerWorld::TIERS)) {
            return false;
        }

        foreach (range((self::ROWS - 1) * self::COLS, self::ROWS * self::COLS - 1) as $i) {
            if (in_array($i, $s['opened'], true)) {
                return true;
            }
        }

        return false;
    }

    private function assertTarget(array $s, int $cell): void
    {
        if ($cell < 0 || $cell >= count($s['grid']) || in_array($cell, $s['opened'], true)) {
            throw new RuntimeException('Этот блок уже выкопан.');
        }

        if (! $this->reachable($s, $cell)) {
            throw new RuntimeException('Копать можно только рядом с открытыми блоками.');
        }
    }

    /** Списать удары. $penalty — штраф (лава, обвал): уходит хоть в ноль. */
    private function spend(array &$s, int $strikes, bool $penalty = false): void
    {
        if (! $penalty && $s['energy'] < 1) {
            throw new RuntimeException('Кирка сломалась — ударов не осталось.');
        }

        $s['energy'] = max(0, $s['energy'] - $strikes);
    }

    private function breakCombo(array &$s): void
    {
        if ($s['meta']['combo'] >= 2) {
            $s['events'][] = ['type' => 'combo_lost', 'combo' => $s['meta']['combo']];
        }

        $s['meta']['combo'] = 0;
    }

    private function comboMult(array $s): float
    {
        return min(self::COMBO_MAX, 1 + self::COMBO_STEP * max(0, $s['meta']['combo'] - 1));
    }

    private function tradeCost(string $offer, int $coins): int
    {
        $trade = MinerWorld::TRADES[$offer];

        return max($trade['min'], (int) floor($coins * $trade['share']));
    }

    /** Сетка яруса. Удача и лихорадка добавляют вес редкой руде. */
    private function generate(int $tier, array $meta): array
    {
        $boost = (1 + self::FORTUNE_WEIGHT * $meta['fortune']) * ($meta['rush'] ? 2 : 1);
        $grid = [];

        for ($row = 0; $row < self::ROWS; $row++) {
            $weights = MinerWorld::weights($tier, $row);

            foreach ($weights as $block => $weight) {
                if (in_array($block, MinerWorld::RARE, true)) {
                    $weights[$block] = (int) round($weight * $boost * 10);
                } else {
                    $weights[$block] = $weight * 10;
                }
            }

            for ($col = 0; $col < self::COLS; $col++) {
                // Ниже первого яруса верхний ряд — площадка спуска из пустой породы
                $block = ($row === 0 && $tier > 1) ? MinerWorld::tier($tier)['rock'] : $this->roll($weights);

                if ($block === 'chest' && random_int(1, 100) <= self::MIMIC_CHANCE) {
                    $block = 'mimic';
                }

                $grid[] = $block;
            }
        }

        return $grid;
    }

    /** Состояние яруса, которое сбрасывается при спуске. */
    private function freshTier(): array
    {
        return ['seen' => [], 'chests' => [], 'villagers' => [], 'map' => false];
    }

    /** meta забега; у забегов до обновления её нет. */
    private function meta(object $run): array
    {
        return (json_decode($run->meta ?? 'null', true) ?: []) + [
            'tier' => 1,
            'combo' => 0,
            'best_combo' => 0,
            'max_energy' => self::ENERGY,
            'dynamite' => 0,
            'pickaxe' => 0,
            'fortune' => 0,
            'lantern' => 0,
            'rush' => false,
            'artifacts' => [],
            'last' => null,
        ] + $this->freshTier();
    }

    private function active(User $user): ?object
    {
        return DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'active')->first();
    }

    private function energyFor(object $profile): int
    {
        return self::ENERGY + self::PICKAXE_STEP * (int) $profile->pickaxe;
    }

    private function cooldownSeconds(object $profile): int
    {
        return self::COOLDOWN_SECONDS - self::CART_STEP * (int) $profile->cart;
    }

    private function cooldownUntil(?object $last, object $profile): ?string
    {
        if (! $last || $last->status !== 'finished' || ! $last->finished_at) {
            return null;
        }

        $until = Carbon::parse($last->finished_at)->addSeconds($this->cooldownSeconds($profile));

        return $until->isFuture() ? $until->toIso8601String() : null;
    }

    private function petLevel(User $user): int
    {
        return max([0, ...array_values(PetService::levels(
            collect($user->profile_pets ?? [])->pluck('inventory_id')->filter()->all()
        ))]);
    }

    /** Клиенту — только открытые блоки (и замеченный бедрок), остальное null. */
    private function present(object $run): array
    {
        $grid = json_decode($run->grid, true);
        $opened = json_decode($run->opened, true);
        $meta = $this->meta($run);
        $coins = (int) $run->coins;
        $s = ['grid' => $grid, 'opened' => $opened, 'meta' => $meta];

        $cells = [];
        foreach ($grid as $i => $block) {
            if (in_array($i, $opened, true)) {
                $cells[] = $block === 'mimic' && ($meta['chests'][$i] ?? null) !== 'mimic' ? 'chest' : $block;
            } elseif (in_array($i, $meta['seen'], true)) {
                $cells[] = $block;
            } else {
                $cells[] = null;
            }
        }

        $reach = [];
        $hints = [];
        foreach (array_keys($grid) as $i) {
            if (! $this->reachable($s, $i)) {
                continue;
            }

            if (! in_array($i, $meta['seen'], true) || $meta['pickaxe'] >= 5) {
                $reach[] = $i;
            }

            if ($cells[$i] === null && ($hint = $this->hint($grid[$i], $meta['lantern'], $meta['map'], $this->lit($meta, $i)))) {
                $hints[$i] = $hint;
            }
        }

        $villagers = [];
        foreach ($meta['villagers'] as $cell => $status) {
            $villagers[$cell] = [
                'status' => $status,
                'offers' => array_map(fn ($key) => [
                    'id' => $key,
                    'text' => MinerWorld::TRADES[$key]['text'],
                    'cost' => $this->tradeCost($key, $coins),
                ], array_keys(MinerWorld::TRADES)),
            ];
        }

        return [
            'id' => $run->id,
            'tier' => $meta['tier'],
            'tier_name' => MinerWorld::tier($meta['tier'])['name'],
            'cells' => $cells,
            'reach' => $reach,
            'hints' => (object) $hints,
            'energy' => (int) $run->energy,
            'max_energy' => $meta['max_energy'],
            'coins' => $coins,
            'combo' => $meta['combo'],
            'combo_mult' => $this->comboMult($s),
            'dynamite' => $meta['dynamite'],
            'chests' => (object) $meta['chests'],
            'villagers' => (object) $villagers,
            'can_descend' => $this->canDescend($s),
            'rush' => $meta['rush'],
            'lantern' => $meta['lantern'],
            'map' => $meta['map'],
        ];
    }

    /**
     * Достаёт ли свет: фонарь в руке освещает клетки вокруг последнего
     * удара (с 3-го уровня — на две клетки), карта жителя — весь ярус.
     */
    private function lit(array $meta, int $cell): bool
    {
        if ($meta['map']) {
            return true;
        }

        if ($meta['lantern'] < 1 || $meta['last'] === null) {
            return false;
        }

        $radius = $meta['lantern'] >= 3 ? 2 : 1;

        return abs(intdiv($cell, self::COLS) - intdiv($meta['last'], self::COLS)) <= $radius
            && abs($cell % self::COLS - $meta['last'] % self::COLS) <= $radius;
    }

    /**
     * Подсказка фонаря о скрытом блоке в круге света ($lit): 1 — опасность,
     * 2 — бедрок, 4 — опасность по всему ярусу, 5 — сундук, житель или
     * артефакт. Руду от породы фонарь не отличает — это удача игрока.
     * Карта жителя ($exact) показывает сам блок.
     */
    private function hint(string $block, int $lantern, bool $exact, bool $lit): ?string
    {
        if ($exact) {
            return $block === 'mimic' ? 'chest' : $block;
        }

        $danger = in_array($block, ['lava', 'gravel'], true);

        if (! $lit) {
            return $lantern >= 4 && $danger ? 'danger' : null;
        }

        return match (true) {
            $danger => 'danger',
            $lantern < 2 => null,
            in_array($block, MinerWorld::WALLS, true) => 'wall',
            $lantern >= 5 && in_array($block, ['chest', 'mimic', 'villager', 'relic'], true) => 'special',
            default => null,
        };
    }

    /** Скрытая клетка, которую можно открыть взрывом (не бедрок). */
    private function closedFloor(array $s, int $cell): bool
    {
        return ! in_array($cell, $s['opened'], true) && ! in_array($s['grid'][$cell], MinerWorld::WALLS, true);
    }

    /** Скрытая клетка рядом (по сторонам) с открытой. */
    private function reachable(array $s, int $cell): bool
    {
        if (in_array($cell, $s['opened'], true)) {
            return false;
        }

        foreach ($this->neighbours($cell) as $n) {
            if (in_array($n, $s['opened'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Соседи по сторонам (с диагоналями — для взрыва). */
    private function neighbours(int $cell, bool $diagonal = false): array
    {
        $row = intdiv($cell, self::COLS);
        $col = $cell % self::COLS;
        $out = [];

        foreach ([-1, 0, 1] as $dr) {
            foreach ([-1, 0, 1] as $dc) {
                if (($dr === 0 && $dc === 0) || (! $diagonal && $dr !== 0 && $dc !== 0)) {
                    continue;
                }

                $r = $row + $dr;
                $c = $col + $dc;

                if ($r >= 0 && $r < self::ROWS && $c >= 0 && $c < self::COLS) {
                    $out[] = $r * self::COLS + $c;
                }
            }
        }

        return $out;
    }

    private function roll(array $weights): string
    {
        $pick = random_int(1, array_sum($weights));

        foreach ($weights as $block => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return $block;
            }
        }

        return array_key_first($weights);
    }
}
