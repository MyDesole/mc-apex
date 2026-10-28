<?php

namespace App\Domains\Games\Services;

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
 * открытой — вглубь шахты. Чем глубже, тем богаче руда. Ударов кирки
 * на забег ограничено; TNT бесплатно взрывает соседей. Где руда, знает
 * только сервер: каждый удар — запрос, клиент видит блок после удара.
 */
class MinerService
{
    public const COLS = 8;
    public const ROWS = 7;
    public const ENERGY = 24;
    public const COOLDOWN_SECONDS = 600;

    /** Блок => монеты за него. */
    public const VALUES = [
        'dirt' => 2,
        'stone' => 5,
        'coal' => 20,
        'copper' => 30,
        'iron' => 50,
        'redstone' => 60,
        'lapis' => 80,
        'gold' => 120,
        'diamond' => 350,
        'emerald' => 550,
        'tnt' => 0,
        'bedrock' => 0,
    ];

    /** Глубина (номер ряда) => веса блоков. Глубже — богаче. */
    private function weights(int $row): array
    {
        return match (true) {
            $row <= 1 => ['dirt' => 50, 'stone' => 30, 'coal' => 14, 'copper' => 6],
            $row <= 3 => ['stone' => 42, 'coal' => 16, 'copper' => 12, 'iron' => 14, 'redstone' => 6, 'tnt' => 4, 'bedrock' => 6],
            $row <= 5 => ['stone' => 36, 'iron' => 14, 'redstone' => 10, 'lapis' => 9, 'gold' => 9, 'diamond' => 3, 'tnt' => 6, 'bedrock' => 13],
            default => ['stone' => 30, 'gold' => 14, 'lapis' => 10, 'diamond' => 9, 'emerald' => 5, 'tnt' => 7, 'bedrock' => 25],
        };
    }

    public function state(User $user): array
    {
        $run = $this->active($user);
        $last = DB::table('miner_runs')->where('user_id', $user->id)->latest('id')->first();

        return [
            'run' => $run ? $this->present($run) : null,
            'cooldown_until' => $run ? null : $this->cooldownUntil($last),
            // Только закрытые забеги: монеты идущего ещё не на счёте
            'best' => (int) DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'finished')->max('coins'),
            'total' => (int) DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'finished')->sum('coins'),
            'runs' => DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'finished')->count(),
            'values' => self::VALUES,
            'energy' => self::ENERGY,
            'cols' => self::COLS,
            'rows' => self::ROWS,
        ];
    }

    public function start(User $user): array
    {
        if ($this->active($user)) {
            throw new RuntimeException('Забег уже идёт.');
        }

        $last = DB::table('miner_runs')->where('user_id', $user->id)->latest('id')->first();

        if ($until = $this->cooldownUntil($last)) {
            $seconds = now()->diffInSeconds(Carbon::parse($until));
            throw new RuntimeException('Кирка остывает — новый забег через ' . max(1, (int) ceil($seconds)) . ' с.');
        }

        $grid = [];
        for ($row = 0; $row < self::ROWS; $row++) {
            for ($col = 0; $col < self::COLS; $col++) {
                $grid[] = $this->roll($this->weights($row));
            }
        }

        // Верхний ряд — открытая поверхность, копать начинаем под ним
        $opened = range(0, self::COLS - 1);

        $id = DB::table('miner_runs')->insertGetId([
            'user_id' => $user->id,
            'grid' => json_encode($grid),
            'opened' => json_encode($opened),
            'energy' => self::ENERGY,
            'coins' => array_sum(array_map(fn ($i) => self::VALUES[$grid[$i]], $opened)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->state($user) + ['started' => $id];
    }

    public function dig(User $user, int $cell): array
    {
        return DB::transaction(function () use ($user, $cell) {
            $run = DB::table('miner_runs')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $run) {
                throw new RuntimeException('Забег не начат.');
            }

            $grid = json_decode($run->grid, true);
            $opened = json_decode($run->opened, true);

            if ($cell < 0 || $cell >= count($grid) || in_array($cell, $opened, true)) {
                throw new RuntimeException('Этот блок уже выкопан.');
            }

            if (! $this->reachable($cell, $opened)) {
                throw new RuntimeException('Копать можно только рядом с открытыми блоками.');
            }

            if ($grid[$cell] === 'bedrock') {
                throw new RuntimeException('Бедрок не копается.');
            }

            $found = [$cell];

            // TNT: бесплатно взрывает соседей (кроме бедрока)
            if ($grid[$cell] === 'tnt') {
                foreach ($this->neighbours($cell, true) as $n) {
                    if (! in_array($n, $opened, true) && $grid[$n] !== 'bedrock') {
                        $found[] = $n;
                    }
                }
            }

            $opened = array_values(array_unique([...$opened, ...$found]));
            $gain = array_sum(array_map(fn ($i) => self::VALUES[$grid[$i]], $found));
            $energy = (int) $run->energy - 1;
            $coins = (int) $run->coins + $gain;

            DB::table('miner_runs')->where('id', $run->id)->update([
                'opened' => json_encode($opened),
                'energy' => $energy,
                'coins' => $coins,
                'updated_at' => now(),
            ]);

            $result = [
                'found' => array_map(fn ($i) => ['cell' => $i, 'block' => $grid[$i]], $found),
                'gain' => $gain,
            ];

            // Кирка сломалась — забег окончен, монеты на счёт
            if ($energy <= 0 || ! $this->anyReachable($grid, $opened)) {
                $result['finished'] = $this->finishRun($user, $run->id, $coins);
            }

            return $result + $this->state($user);
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

            return ['finished' => $this->finishRun($user, $run->id, (int) $run->coins)] + $this->state($user);
        });
    }

    private function finishRun(User $user, int $runId, int $coins): array
    {
        DB::table('miner_runs')->where('id', $runId)->update([
            'status' => 'finished',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        if ($coins > 0) {
            CoinService::credit($user, $coins, CoinTransaction::SOURCE_OTHER, 'Шахтёр: забег', "miner:{$runId}");
        }

        return ['coins' => $coins, 'balance' => (int) $user->fresh()->apex_coins];
    }

    private function active(User $user): ?object
    {
        return DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'active')->first();
    }

    private function cooldownUntil(?object $last): ?string
    {
        if (! $last || $last->status !== 'finished' || ! $last->finished_at) {
            return null;
        }

        $until = Carbon::parse($last->finished_at)->addSeconds(self::COOLDOWN_SECONDS);

        return $until->isFuture() ? $until->toIso8601String() : null;
    }

    /** Клиенту — только открытые блоки, остальное «?». */
    private function present(object $run): array
    {
        $grid = json_decode($run->grid, true);
        $opened = json_decode($run->opened, true);

        return [
            'id' => $run->id,
            'cells' => array_map(fn ($i) => in_array($i, $opened, true) ? $grid[$i] : null, array_keys($grid)),
            'energy' => (int) $run->energy,
            'coins' => (int) $run->coins,
        ];
    }

    private function reachable(int $cell, array $opened): bool
    {
        foreach ($this->neighbours($cell) as $n) {
            if (in_array($n, $opened, true)) {
                return true;
            }
        }

        return false;
    }

    private function anyReachable(array $grid, array $opened): bool
    {
        foreach (array_keys($grid) as $cell) {
            if (! in_array($cell, $opened, true) && $grid[$cell] !== 'bedrock' && $this->reachable($cell, $opened)) {
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
