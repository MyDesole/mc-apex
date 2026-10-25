<?php

namespace App\Console\Commands;

use App\Domains\Activity\Models\Activity;
use App\Domains\Activity\Observers\ActivityObserver;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Заполнить ленту «Сейчас на APEX» из уже накопленной истории.
 *
 * Лента пишется с момента появления, а тир-тесты, войны и ачивки
 * копились и раньше. Команда восстанавливает события с исходными датами,
 * ничего не рассылая. Повторный запуск без --fresh ничего не делает,
 * чтобы события не задваивались.
 */
class ActivityBackfillCommand extends Command
{
    protected $signature = 'apex:activity-backfill
        {--fresh : Очистить ленту и собрать заново}
        {--days=90 : За сколько последних дней собирать события}';

    protected $description = 'Заполнить ленту событий из истории тир-тестов, войн, турниров и ачивок';

    /** @var array<int, array<string, mixed>> */
    private array $pending = [];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            Activity::query()->delete();
        } elseif (Activity::query()->exists()) {
            $this->warn('Лента уже не пуста. Запустите с --fresh, чтобы собрать заново.');

            return self::SUCCESS;
        }

        $since = now()->subDays(max(1, (int) $this->option('days')));

        $counts = [
            'тир-тесты' => $this->tierTests($since),
            'ачивки' => $this->achievements($since),
            'войны' => $this->wars($since),
            'турниры' => $this->tournamentWins($since),
            'кланы' => $this->clans($since),
            'майнкрафт' => $this->minecraftLinks($since),
        ];

        // Лента сортируется по id, поэтому вставляем в хронологическом порядке
        usort($this->pending, fn ($a, $b) => strcmp((string) $a['created_at'], (string) $b['created_at']));

        DB::transaction(function () {
            foreach ($this->pending as $row) {
                Activity::create($row);
            }
        });

        foreach ($counts as $label => $count) {
            $this->line("{$label}: {$count}");
        }

        $this->info('Готово: ' . array_sum($counts) . ' событий.');

        return self::SUCCESS;
    }

    private function tierTests($since): int
    {
        return TierTest::query()
            ->where('status', 'completed')
            ->whereNotNull('result_tier')
            ->where('completed_at', '>=', $since)
            ->get()
            ->each(fn (TierTest $test) => $this->add(Activity::TIER_ASSIGNED, $test->user_id, [
                'tier' => $test->result_tier,
                'score' => (float) $test->result_score,
                'mode' => $test->mode,
            ], $test->completed_at))
            ->count();
    }

    private function achievements($since): int
    {
        return DB::table('user_achievements')
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.earned_at', '>=', $since)
            ->get([
                'user_achievements.user_id',
                'user_achievements.earned_at',
                'achievements.id',
                'achievements.name',
                'achievements.icon',
                'achievements.color',
                'achievements.rarity',
            ])
            ->each(fn ($row) => $this->add(Activity::ACHIEVEMENT_EARNED, $row->user_id, [
                'achievement' => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'icon' => $row->icon,
                    'color' => $row->color,
                    'rarity' => $row->rarity,
                ],
            ], $row->earned_at))
            ->count();
    }

    private function wars($since): int
    {
        return ClanWar::query()
            ->where('status', 'completed')
            ->where('updated_at', '>=', $since)
            ->get()
            ->filter(function (ClanWar $war) {
                $challenger = Clan::find($war->challenger_clan_id);
                $opponent = Clan::find($war->opponent_clan_id);

                if (! $challenger || ! $opponent) {
                    return false;
                }

                $this->add(Activity::CLAN_WAR_FINISHED, null, [
                    'challenger' => ActivityObserver::clanSnapshot($challenger),
                    'opponent' => ActivityObserver::clanSnapshot($opponent),
                    'challenger_score' => (int) $war->challenger_score,
                    'opponent_score' => (int) $war->opponent_score,
                    'winner_clan_id' => $war->winner_clan_id,
                    'outcome' => $war->outcome,
                ], $war->updated_at);

                return true;
            })
            ->count();
    }

    private function tournamentWins($since): int
    {
        return TournamentWin::query()
            ->with('tournament:id,name')
            ->where('created_at', '>=', $since)
            ->get()
            ->each(fn (TournamentWin $win) => $this->add(Activity::TOURNAMENT_WON, $win->user_id, [
                'tournament' => $win->tournament
                    ? ['id' => $win->tournament->id, 'name' => $win->tournament->name]
                    : null,
            ], $win->created_at))
            ->count();
    }

    private function clans($since): int
    {
        return Clan::query()
            ->where('created_at', '>=', $since)
            ->get()
            ->each(fn (Clan $clan) => $this->add(Activity::CLAN_CREATED, $clan->leader_id, [
                'clan' => ActivityObserver::clanSnapshot($clan),
            ], $clan->created_at))
            ->count();
    }

    private function minecraftLinks($since): int
    {
        return User::query()
            ->whereNotNull('minecraft_linked_at')
            ->where('minecraft_linked_at', '>=', $since)
            ->get(['id', 'minecraft_username', 'minecraft_linked_at'])
            ->each(fn (User $user) => $this->add(Activity::MINECRAFT_LINKED, $user->id, [
                'nickname' => $user->minecraft_username,
            ], $user->minecraft_linked_at))
            ->count();
    }

    /** Событие с исходной датой; запишется без рассылки в handle(). */
    private function add(string $type, ?int $actorId, array $payload, $at): void
    {
        $this->pending[] = [
            'type' => $type,
            'actor_id' => $actorId,
            'payload' => $payload,
            'created_at' => \Illuminate\Support\Carbon::parse($at ?? now())->toDateTimeString(),
        ];
    }
}
