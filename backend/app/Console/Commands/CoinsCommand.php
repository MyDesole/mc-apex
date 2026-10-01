<?php

namespace App\Console\Commands;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Console\Command;

/**
 * Начисление и списание ApexCoin из консоли.
 *
 * Примеры:
 *   php artisan apex:coins 6                          # показать баланс
 *   php artisan apex:coins 6 1000000                  # выдать 1 000 000
 *   php artisan apex:coins 6 1000000 --reason="тест"  # с причиной
 *   php artisan apex:coins 6 500 --remove             # списать 500
 *   php artisan apex:coins all 100 --force            # выдать всем игрокам
 *
 * Про минус: Symfony Console принимает «-500» за опцию, поэтому для списания
 * используй флаг --remove с положительной суммой.
 */
class CoinsCommand extends Command
{
    protected $signature = 'apex:coins
                            {user : ID игрока или all — для всех игроков}
                            {amount? : Сколько начислить (для списания — с флагом --remove)}
                            {--remove : Списать указанную сумму вместо начисления}
                            {--reason= : Причина, попадёт в леджер}
                            {--force : Не спрашивать подтверждения при массовой выдаче}';

    protected $description = 'Выдать или списать ApexCoin игроку по ID (все операции пишутся в леджер)';

    public function handle(): int
    {
        $target = (string) $this->argument('user');
        $amount = $this->argument('amount');

        // Без суммы — просто показываем баланс
        if ($amount === null) {
            return $this->showBalance($target);
        }

        $amount = (int) $amount;

        // --remove делает сумму отрицательной; отрицательный аргумент тоже понимаем
        if ($this->option('remove') && $amount > 0) {
            $amount = -$amount;
        }

        if ($amount === 0) {
            $this->error('Сумма не может быть нулевой.');
            return self::FAILURE;
        }

        $reason = $this->option('reason')
            ?: ($amount > 0 ? 'Начисление из консоли' : 'Списание из консоли');

        // Массовая выдача
        if ($target === 'all') {
            return $this->creditEveryone($amount, $reason);
        }

        if (! is_numeric($target)) {
            $this->error("ID игрока должен быть числом или 'all', получено: {$target}");
            return self::FAILURE;
        }

        $user = User::find((int) $target);

        if (! $user) {
            $this->error("Игрок с ID {$target} не найден.");
            return self::FAILURE;
        }

        return $this->creditOne($user, $amount, $reason);
    }

    private function creditOne(User $user, int $amount, string $reason): int
    {
        $before = (int) $user->apex_coins;

        try {
            $applied = CoinService::credit(
                $user,
                $amount,
                CoinTransaction::SOURCE_ADMIN,
                $reason,
                null,
                ['meta' => ['via' => 'artisan apex:coins']]
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($applied === 0) {
            $this->warn('Ничего не изменилось (сумма 0 или операция уже применена).');
            return self::SUCCESS;
        }

        $after = (int) $user->fresh()->apex_coins;

        $this->table(
            ['Игрок', 'ID', 'Было', 'Операция', 'Стало'],
            [[
                $user->username,
                $user->id,
                $before,
                ($applied > 0 ? '+' : '') . $applied,
                $after,
            ]]
        );

        $this->info('Записано в леджер: ' . $reason);

        return self::SUCCESS;
    }

    private function creditEveryone(int $amount, string $reason): int
    {
        $total = User::count();

        if ($total === 0) {
            $this->warn('В базе нет игроков.');
            return self::SUCCESS;
        }

        $this->warn("Будет изменён баланс у {$total} игроков на " . ($amount > 0 ? '+' : '') . $amount . ' ApexCoin.');

        if (! $this->option('force') && ! $this->confirm('Продолжить?', false)) {
            $this->error('Отменено.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $done = 0;
        $failed = 0;

        User::query()->chunkById(200, function ($users) use ($amount, $reason, $bar, &$done, &$failed) {
            foreach ($users as $user) {
                try {
                    if (CoinService::credit($user, $amount, CoinTransaction::SOURCE_ADMIN, $reason) !== 0) {
                        $done++;
                    }
                } catch (\RuntimeException $e) {
                    $failed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Начислено: {$done}, пропущено с ошибкой: {$failed}.");

        return self::SUCCESS;
    }

    private function showBalance(string $target): int
    {
        if ($target === 'all') {
            $this->table(
                ['Всего игроков', 'Суммарный баланс', 'Всего потрачено'],
                [[
                    User::count(),
                    (int) User::sum('apex_coins'),
                    (int) User::sum('apex_coins_spent'),
                ]]
            );

            return self::SUCCESS;
        }

        $user = User::find((int) $target);

        if (! $user) {
            $this->error("Игрок с ID {$target} не найден.");
            return self::FAILURE;
        }

        $this->table(
            ['Игрок', 'ID', 'Баланс', 'Потрачено', 'Операций в леджере'],
            [[
                $user->username,
                $user->id,
                $user->apex_coins,
                $user->apex_coins_spent,
                $user->coinTransactions()->count(),
            ]]
        );

        return self::SUCCESS;
    }
}
