<?php

namespace App\Console\Commands;

use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\TierTest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAspectsTo20 extends Command
{
    protected $signature = 'apex:migrate-aspects
                            {--dry-run : Только показать, что будет изменено, без записи в БД}
                            {--force : Не спрашивать подтверждения}';

    protected $description = 'Мигрирует аспекты со шкалы 0–10 на 0–20, пересчитывает tier_score и tier у всех юзеров, обновляет tier_tests';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('=== APEX · Миграция аспектов на шкалу 0–20 ===');
        $this->newLine();

        $pvpCount   = PlayerAspectPvp::count();
        $bwCount    = PlayerAspectBedwars::count();
        $usersCount = User::count();
        $testsCount = TierTest::count();

        $this->table(
            ['Таблица', 'Записей'],
            [
                ['player_aspects_pvp', $pvpCount],
                ['player_aspects_bedwars', $bwCount],
                ['users', $usersCount],
                ['tier_tests', $testsCount],
            ]
        );

        if ($dryRun) {
            $this->warn('Режим --dry-run. В БД ничего не записывается.');
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm('Продолжить миграцию? Убедись, что сделан бэкап БД.', false)) {
                $this->error('Отменено пользователем.');
                return self::FAILURE;
            }
        }

        $this->newLine();
        $startedAt = now();

        try {
            DB::transaction(function () use ($dryRun) {
                $this->migratePvpAspects($dryRun);
                $this->migrateBedwarsAspects($dryRun);
                $this->recalcUsers($dryRun);
                $this->migrateTierTests($dryRun);
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Ошибка: ' . $e->getMessage());
            $this->error('Все изменения откачены транзакцией.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Готово за %s сек.',
            number_format(now()->diffInSeconds($startedAt), 2)
        ));

        if ($dryRun) {
            $this->warn('Никаких изменений не сохранено (--dry-run).');
        }

        return self::SUCCESS;
    }

    private function migratePvpAspects(bool $dryRun): void
    {
        $this->line('→ Аспекты PvP (×2)');

        $bar = $this->output->createProgressBar(PlayerAspectPvp::count());
        $bar->start();

        PlayerAspectPvp::query()->chunkById(200, function ($rows) use ($dryRun, $bar) {
            foreach ($rows as $a) {
                if (!$dryRun) {
                    $a->update([
                        'block_placing' => min(20, (int) $a->block_placing * 2),
                        'rotka'         => min(20, (int) $a->rotka * 2),
                        'movement'      => min(20, (int) $a->movement * 2),
                        'aim'           => min(20, (int) $a->aim * 2),
                        'game_sense'    => min(20, (int) $a->game_sense * 2),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
    }

    private function migrateBedwarsAspects(bool $dryRun): void
    {
        $this->line('→ Аспекты BedWars (×2)');

        $bar = $this->output->createProgressBar(PlayerAspectBedwars::count());
        $bar->start();

        PlayerAspectBedwars::query()->chunkById(200, function ($rows) use ($dryRun, $bar) {
            foreach ($rows as $a) {
                if (!$dryRun) {
                    $a->update([
                        'pvp'        => min(20, (int) $a->pvp * 2),
                        'game_sense' => min(20, (int) $a->game_sense * 2),
                        'bed_play'   => min(20, (int) $a->bed_play * 2),
                        'teamplay'   => min(20, (int) $a->teamplay * 2),
                        'building'   => min(20, (int) $a->building * 2),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
    }

    private function recalcUsers(bool $dryRun): void
    {
        $this->line('→ Пересчёт tier_score и tier у юзеров');

        $bar = $this->output->createProgressBar(User::count());
        $bar->start();

        User::query()
            ->with(['aspectPvp', 'aspectBedwars'])
            ->chunkById(200, function ($users) use ($dryRun, $bar) {
                foreach ($users as $u) {
                    $pvpScore = $u->aspectPvp?->sum() ?? 0;
                    $bwScore  = $u->aspectBedwars?->sum() ?? 0;
                    $best = max($pvpScore, $bwScore);

                    $tier = match (true) {
                        $best >= 71 => 'A',
                        $best >= 56 => 'B',
                        $best >= 41 => 'C',
                        $best >= 21 => 'D',
                        default     => 'E',
                    };

                    if (!$dryRun) {
                        // S и S+ не понижаем — они выдаются за турниры
                        if (!in_array($u->tier, ['S', 'S+'], true)) {
                            $u->tier = $tier;
                        }
                        $u->tier_score = $best;
                        $u->save();
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
    }

    private function migrateTierTests(bool $dryRun): void
    {
        $this->line('→ Пересчёт tier_tests (aspects и result_score)');

        $bar = $this->output->createProgressBar(TierTest::count());
        $bar->start();

        TierTest::query()->chunkById(200, function ($tests) use ($dryRun, $bar) {
            foreach ($tests as $t) {
                if (!$dryRun && $t->aspects && is_array($t->aspects)) {
                    $newAspects = [];

                    foreach ($t->aspects as $key => $value) {
                        if (is_numeric($value)) {
                            $newAspects[$key] = min(20, (int) $value * 2);
                        } else {
                            $newAspects[$key] = $value;
                        }
                    }

                    $sum = array_sum(
                        array_filter($newAspects, 'is_numeric')
                    );

                    $t->update([
                        'aspects'      => $newAspects,
                        'result_score' => min(100, $sum),
                    ]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
    }
}
