<?php

namespace App\Domains\Games\Services;

use App\Domains\Games\Support\MinerWorld;
use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Всё, что у шахтёра остаётся между забегами: улучшения за ApexCoin,
 * коллекция артефактов, ежедневные задания, топ недели и золотая
 * лихорадка по расписанию.
 */
class MinerProgress
{
    /** Задания, где прогресс — лучшее значение, а не сумма. */
    private const MAX_QUESTS = ['combo', 'nether'];

    /** Профиль шахтёра; создаётся при первом обращении. */
    public function profile(User $user): object
    {
        $profile = DB::table('miner_profiles')->where('user_id', $user->id)->first();

        if ($profile) {
            return $profile;
        }

        DB::table('miner_profiles')->insertOrIgnore([
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('miner_profiles')->where('user_id', $user->id)->first();
    }

    /** Улучшения для клиента: уровень, максимум, цена следующего. */
    public function upgrades(object $profile): array
    {
        $out = [];

        foreach (MinerWorld::UPGRADES as $key => $upgrade) {
            $level = (int) $profile->{$key};
            $out[$key] = [
                'name' => $upgrade['name'],
                'about' => $upgrade['about'],
                'level' => $level,
                'max' => count($upgrade['prices']),
                'price' => $upgrade['prices'][$level] ?? null,
            ];
        }

        return $out;
    }

    public function upgrade(User $user, string $key): void
    {
        if (! isset(MinerWorld::UPGRADES[$key])) {
            throw new RuntimeException('Такого улучшения нет.');
        }

        $this->profile($user);

        DB::transaction(function () use ($user, $key) {
            $profile = DB::table('miner_profiles')->where('user_id', $user->id)->lockForUpdate()->first();
            $level = (int) $profile->{$key};
            $price = MinerWorld::UPGRADES[$key]['prices'][$level] ?? null;

            if ($price === null) {
                throw new RuntimeException('Уже максимальный уровень.');
            }

            if ((int) $user->fresh()->apex_coins < $price) {
                throw new RuntimeException("Нужно {$price} ApexCoin.");
            }

            CoinService::debit($user, $price, CoinTransaction::SOURCE_PURCHASE, 'Шахтёр: ' . MinerWorld::UPGRADES[$key]['name'] . ' ' . ($level + 1) . ' ур.');

            DB::table('miner_profiles')->where('user_id', $user->id)->update([
                $key => $level + 1,
                'updated_at' => now(),
            ]);
        });
    }

    /** Коллекция для клиента: все артефакты, найденные — со счётчиком. */
    public function artifacts(object $profile): array
    {
        $found = json_decode($profile->artifacts ?? '[]', true) ?: [];

        return [
            'items' => array_map(fn ($id) => [
                'id' => $id,
                'name' => MinerWorld::ARTIFACTS[$id]['name'],
                'tier' => MinerWorld::ARTIFACTS[$id]['tier'],
                'count' => (int) ($found[$id] ?? 0),
            ], array_keys(MinerWorld::ARTIFACTS)),
            'bonus' => $this->artifactBonus($profile),
        ];
    }

    /** Надбавка к добыче за коллекцию: доля, например 0.06. */
    public function artifactBonus(object $profile): float
    {
        $found = array_filter(json_decode($profile->artifacts ?? '[]', true) ?: []);
        $bonus = count($found) * MinerWorld::ARTIFACT_BONUS;

        if (count($found) === count(MinerWorld::ARTIFACTS)) {
            $bonus += MinerWorld::SET_BONUS;
        }

        return round($bonus, 2);
    }

    /** Записать находку; true — такого артефакта ещё не было. */
    public function addArtifact(int $userId, string $id): bool
    {
        $profile = DB::table('miner_profiles')->where('user_id', $userId)->lockForUpdate()->first();
        $found = json_decode($profile->artifacts ?? '[]', true) ?: [];
        $isNew = empty($found[$id]);
        $found[$id] = (int) ($found[$id] ?? 0) + 1;

        DB::table('miner_profiles')->where('user_id', $userId)->update([
            'artifacts' => json_encode($found),
            'updated_at' => now(),
        ]);

        return $isNew;
    }

    /** Задания на сегодня: три из пула, у каждого игрока свои. */
    public function quests(User $user, ?object $profile = null): array
    {
        $profile ??= $this->profile($user);
        $today = $this->todayQuests($user->id, $profile);

        return array_map(fn ($q) => [
            'id' => $q['id'],
            'text' => MinerWorld::QUESTS[$q['id']]['text'],
            'goal' => MinerWorld::QUESTS[$q['id']]['goal'],
            'reward' => MinerWorld::QUESTS[$q['id']]['reward'],
            'progress' => min($q['progress'], MinerWorld::QUESTS[$q['id']]['goal']),
            'claimed' => $q['claimed'],
        ], $today['items']);
    }

    /**
     * Продвинуть задания. $counters: ключ задания => сколько добавить
     * (для «лучших» — достигнутое значение).
     */
    public function track(int $userId, array $counters): void
    {
        $counters = array_filter($counters);
        if (! $counters) {
            return;
        }

        $profile = DB::table('miner_profiles')->where('user_id', $userId)->lockForUpdate()->first();
        if (! $profile) {
            return;
        }

        $today = $this->todayQuests($userId, $profile);

        foreach ($today['items'] as &$q) {
            if (! isset($counters[$q['id']])) {
                continue;
            }

            $q['progress'] = in_array($q['id'], self::MAX_QUESTS, true)
                ? max($q['progress'], (int) $counters[$q['id']])
                : $q['progress'] + (int) $counters[$q['id']];
        }
        unset($q);

        DB::table('miner_profiles')->where('user_id', $userId)->update([
            'quests' => json_encode($today),
            'updated_at' => now(),
        ]);
    }

    public function claim(User $user, string $id): int
    {
        $this->profile($user);

        return DB::transaction(function () use ($user, $id) {
            $profile = DB::table('miner_profiles')->where('user_id', $user->id)->lockForUpdate()->first();
            $today = $this->todayQuests($user->id, $profile);
            $index = array_search($id, array_column($today['items'], 'id'), true);

            if ($index === false) {
                throw new RuntimeException('Сегодня такого задания нет.');
            }

            $quest = $today['items'][$index];
            $def = MinerWorld::QUESTS[$id];

            if ($quest['claimed']) {
                throw new RuntimeException('Награда уже получена.');
            }

            if ($quest['progress'] < $def['goal']) {
                throw new RuntimeException('Задание ещё не выполнено.');
            }

            $today['items'][$index]['claimed'] = true;
            DB::table('miner_profiles')->where('user_id', $user->id)->update([
                'quests' => json_encode($today),
                'updated_at' => now(),
            ]);

            CoinService::credit($user, $def['reward'], CoinTransaction::SOURCE_OTHER, 'Шахтёр: задание «' . $def['text'] . '»', "miner-quest:{$user->id}:{$today['date']}:{$id}");

            return $def['reward'];
        });
    }

    /** Топ недели по лучшему забегу и место игрока. */
    public function leaderboard(User $user): array
    {
        $start = now()->startOfWeek();

        $rows = DB::table('miner_runs')
            ->where('status', 'finished')
            ->where('finished_at', '>=', $start)
            ->selectRaw('user_id, MAX(coins) AS best_coins')
            ->groupBy('user_id')
            ->orderByDesc('best_coins')
            ->orderBy('user_id')
            ->limit(10)
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        $mine = (int) DB::table('miner_runs')
            ->where('user_id', $user->id)
            ->where('status', 'finished')
            ->where('finished_at', '>=', $start)
            ->max('coins');

        $place = null;
        if ($mine > 0) {
            // Сколько игроков с лучшим забегом выше моего
            $place = 1 + DB::table('miner_runs')
                ->where('status', 'finished')
                ->where('finished_at', '>=', $start)
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('MAX(coins) > ?', [$mine])
                ->get()
                ->count();
        }

        return [
            'ends_at' => $start->copy()->addWeek()->toIso8601String(),
            'prizes' => array_map(fn ($p) => ['coins' => $p[0], 'case' => LootCases::find($p[1])['name'] ?? $p[1]], MinerWorld::WEEK_PRIZES),
            'rows' => $rows->values()->map(fn ($r, $i) => [
                'place' => $i + 1,
                'coins' => (int) $r->best_coins,
                'user' => isset($users[$r->user_id])
                    ? ['id' => $r->user_id, 'username' => $users[$r->user_id]->username, 'avatar_url' => $users[$r->user_id]->avatar_url]
                    : null,
            ])->all(),
            'me' => ['best' => $mine, 'place' => $place],
        ];
    }

    /**
     * Призы тройке лучших за неделю (по умолчанию прошлую). Повторный
     * запуск ничего не выдаёт: монеты идут с ключом идемпотентности.
     */
    public function awardWeek(?Carbon $weekStart = null): int
    {
        $start = ($weekStart ?? now()->subWeek())->copy()->startOfWeek();
        $end = $start->copy()->addWeek();
        $week = $start->toDateString();

        $top = DB::table('miner_runs')
            ->where('status', 'finished')
            ->where('finished_at', '>=', $start)
            ->where('finished_at', '<', $end)
            ->selectRaw('user_id, MAX(coins) AS best_coins')
            ->groupBy('user_id')
            ->orderByDesc('best_coins')
            ->orderBy('user_id')
            ->limit(count(MinerWorld::WEEK_PRIZES))
            ->get();

        $awarded = 0;

        foreach ($top->values() as $index => $row) {
            $place = $index + 1;
            [$coins, $case] = MinerWorld::WEEK_PRIZES[$place];
            $user = User::find($row->user_id);

            if (! $user) {
                continue;
            }

            $paid = DB::transaction(function () use ($user, $coins, $case, $place, $week) {
                $paid = CoinService::credit($user, $coins, CoinTransaction::SOURCE_OTHER, "Шахтёр недели: {$place} место", "miner-week:{$week}:{$place}");

                if ($paid > 0) {
                    LootCaseService::grantKeys($user, $case);
                }

                return $paid;
            });

            if ($paid <= 0) {
                continue;
            }

            $caseName = LootCases::find($case)['name'] ?? $case;
            $user->notify(new ModerationNotification(
                'miner_week',
                "Ты {$place} в топе шахтёров недели! Награда: {$coins} ApexCoin и «{$caseName}» бесплатно.",
                ['place' => $place, 'coins' => $coins, 'case' => $case, 'link' => '/miner'],
            ));
            $awarded++;
        }

        return $awarded;
    }

    /**
     * Золотая лихорадка: три часа в сутки, одни и те же для всех —
     * выбираются по дате, без хранения. Пока идёт, редкой руды вдвое больше.
     */
    public static function rush(?Carbon $at = null): array
    {
        $at ??= now();
        $hours = self::rushHours($at->toDateString());
        $active = in_array($at->hour, $hours, true);

        $next = null;
        foreach ([$at->copy(), $at->copy()->addDay()] as $day) {
            foreach (self::rushHours($day->toDateString()) as $hour) {
                $begin = $day->copy()->setTime($hour, 0);
                if ($begin->greaterThan($at)) {
                    $next = $begin;
                    break 2;
                }
            }
        }

        return [
            'active' => $active,
            'until' => $active ? $at->copy()->setTime($at->hour, 0)->addHour()->toIso8601String() : null,
            'next' => $next?->toIso8601String(),
        ];
    }

    /** @return int[] часы лихорадки в этот день, по возрастанию */
    public static function rushHours(string $date): array
    {
        $hours = range(12, 23);
        usort($hours, fn ($a, $b) => crc32("miner-rush:{$date}:{$a}") <=> crc32("miner-rush:{$date}:{$b}"));
        $picked = array_slice($hours, 0, 3);
        sort($picked);

        return $picked;
    }

    /** Задания на сегодня из профиля; в новый день — новая тройка. */
    private function todayQuests(int $userId, object $profile): array
    {
        $date = today()->toDateString();
        $saved = json_decode($profile->quests ?? 'null', true);

        if (($saved['date'] ?? null) === $date) {
            return $saved;
        }

        $ids = array_keys(MinerWorld::QUESTS);
        usort($ids, fn ($a, $b) => crc32("{$userId}:{$date}:{$a}") <=> crc32("{$userId}:{$date}:{$b}"));

        return [
            'date' => $date,
            'items' => array_map(fn ($id) => ['id' => $id, 'progress' => 0, 'claimed' => false], array_slice($ids, 0, 3)),
        ];
    }
}
