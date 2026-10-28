<?php

namespace App\Console\Commands;

use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Награды «Самых уважаемых» за прошедшую неделю (пн 00:00 — вс 23:59).
 *
 * Тройке лидеров по респектам — монеты и ключ от сундука. Запускается
 * планировщиком в понедельник в 00:05; повторный запуск ничего не выдаёт
 * (одна запись на место в respect_week_rewards).
 */
class RespectWeeklyRewardsCommand extends Command
{
    protected $signature = 'respects:weekly-rewards {--week= : Понедельник недели, YYYY-MM-DD (по умолчанию прошлая)}';

    protected $description = 'Выдать награды тройке самых уважаемых за неделю';

    /** Место => [монеты, кейс для ключа]. */
    public const REWARDS = [
        1 => [50000, 'royal'],
        2 => [30000, 'seeker'],
        3 => [10000, 'seeker'],
    ];

    public function handle(): int
    {
        $start = $this->option('week')
            ? Carbon::parse($this->option('week'))->startOfWeek()
            : now()->subWeek()->startOfWeek();
        $end = $start->copy()->addWeek();

        $top = DB::table('profile_respects')
            ->whereNull('revoked_at')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->select('owner_id', DB::raw('count(*) as respects'), DB::raw('min(created_at) as first_at'))
            ->groupBy('owner_id')
            // При равенстве выше тот, кто набрал первым
            ->orderByDesc('respects')
            ->orderBy('first_at')
            ->limit(count(self::REWARDS))
            ->get();

        $week = $start->toDateString();

        foreach ($top->values() as $index => $row) {
            $place = $index + 1;
            [$coins, $case] = self::REWARDS[$place];

            $user = User::find($row->owner_id);
            if (! $user) {
                continue;
            }

            $created = DB::transaction(function () use ($week, $place, $user, $row, $coins, $case) {
                $inserted = DB::table('respect_week_rewards')->insertOrIgnore([
                    'week_start' => $week,
                    'place' => $place,
                    'user_id' => $user->id,
                    'respects' => (int) $row->respects,
                    'coins' => $coins,
                    'case_slug' => $case,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! $inserted) {
                    return false;
                }

                CoinService::credit(
                    $user,
                    $coins,
                    CoinTransaction::SOURCE_OTHER,
                    "Самые уважаемые недели: {$place} место",
                    "respect-week:{$week}:{$place}",
                );
                LootCaseService::grantKeys($user, $case);

                return true;
            });

            if (! $created) {
                continue;
            }

            $caseName = LootCases::find($case)['name'] ?? $case;
            $user->notify(new ModerationNotification(
                'respect_reward',
                "Ты на {$place} месте среди самых уважаемых недели! Награда: {$coins} ApexCoin и «{$caseName}» бесплатно.",
                ['place' => $place, 'coins' => $coins, 'case' => $case, 'link' => '/cases'],
            ));

            $this->info("{$place}: {$user->username} ({$row->respects}) — {$coins} + {$case}");
        }

        return self::SUCCESS;
    }
}
