<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Console\Command;

/**
 * Пересчёт ачивок и доплата за них.
 *
 * Нужна, когда ачивка была выставлена без награды (источник начислений
 * был выключен или у ачивки не задан coin_reward), либо когда состав
 * ачивок менялся и часть из них у игроков не проставлена.
 *
 * Идемпотентна: повторный запуск не начислит монеты второй раз —
 * награда помечается ключом achievement:{user}:{achievement}.
 */
class AchievementsRecalculateCommand extends Command
{
    protected $signature = 'apex:achievements
        {--user= : Только один игрок: id или ник}
        {--dry-run : Показать, что будет сделано, ничего не меняя}
        {--force : Не спрашивать подтверждение}';

    protected $description = 'Пересчитать ачивки всех игроков и начислить ApexCoin за неоплаченные';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = null;

        if ($target = $this->option('user')) {
            $user = ctype_digit((string) $target)
                ? User::find((int) $target)
                : User::where('username', $target)->first();

            if (! $user) {
                $this->error("Игрок «{$target}» не найден.");

                return self::FAILURE;
            }

            $userId = $user->id;
            $this->line("Игрок: {$user->username} (#{$user->id})");
        }

        $total = $userId ? 1 : User::count();

        if ($total === 0) {
            $this->info('В базе нет игроков.');

            return self::SUCCESS;
        }

        if (! $dryRun && ! $this->option('force') && ! $this->confirm(
            "Пересчитать ачивки у {$total} игроков и начислить монеты?"
        )) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $this->line($dryRun
            ? "Режим проверки: изменения не сохраняются."
            : "Пересчитываю ачивки…");

        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $report = AchievementService::syncAchievements($userId, $dryRun);

        $bar->advance($report['users']);
        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Игроков обработано', $report['users']],
                ['Ачивок доставлено', $report['granted']],
                ['Ачивок без награды', $report['unpaid']],
                [$dryRun ? 'К начислению' : 'Награждено', $report['paid']],
                ['Сумма ApexCoin', number_format($report['paid_amount'], 0, '.', ' ')],
                ['Ошибок', $report['failed']],
            ]
        );

        if ($dryRun) {
            $this->warn("Режим проверки: база не изменена.");

            return self::SUCCESS;
        }

        if ($report['paid'] === 0) {
            $this->info('Все ачивки уже оплачено — доплат не потребовалось.');
        } else {
            $this->info(sprintf(
                'Начислено %s ApexCoin за %d ачивок.',
                number_format($report['paid_amount'], 0, '.', ' '),
                $report['paid']
            ));
        }

        if ($report['failed'] > 0) {
            $this->warn("Не удалось обработать игроков: {$report['failed']}. Подробности в логах.");
        }

        return self::SUCCESS;
    }
}
