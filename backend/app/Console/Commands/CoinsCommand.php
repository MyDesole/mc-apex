<?php

namespace App\Console\Commands;

use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Console\Command;

class CoinsCommand extends Command
{
    protected $signature = 'apex:coins
                            {user : ID игрока или all — для всех игроков}
                            {amount? : Сколько начислить или списать}
                            {--remove : Списать указанную сумму}
                            {--reset : Обнулить всё, кроме daily bonus и подарков}
                            {--reason= : Причина операции}
                            {--force : Не спрашивать подтверждения}';

    protected $description = 'Управление ApexCoin игрока или всех игроков';

    public function handle(): int
    {
        $target = (string) $this->argument('user');
        $amount = $this->argument('amount');

        // Обнуление с сохранением daily bonus и подарков
        if ($this->option('reset')) {
            if ($target === 'all') {
                return $this->resetEveryone();
            }

            return $this->resetOne($target);
        }

        // Без суммы — показать баланс
        if ($amount === null) {
            return $this->showBalance($target);
        }

        $amount = (int) $amount;

        if ($this->option('remove') && $amount > 0) {
            $amount = -$amount;
        }

        if ($amount === 0) {
            $this->error('Сумма не может быть нулевой.');
            return self::FAILURE;
        }

        $reason = $this->option('reason')
            ?: ($amount > 0
                ? 'Начисление из консоли'
                : 'Списание из консоли');

        if ($target === 'all') {
            return $this->creditEveryone($amount, $reason);
        }

        if (! is_numeric($target)) {
            $this->error("ID игрока должен быть числом или 'all'.");
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
            $this->warn('Баланс не изменился.');
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

        $this->warn(
            "Будет изменён баланс у {$total} игроков на "
            . ($amount > 0 ? '+' : '')
            . "{$amount} ApexCoin."
        );

        if (
            ! $this->option('force')
            && ! $this->confirm('Продолжить?', false)
        ) {
            $this->error('Отменено.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $done = 0;
        $failed = 0;

        User::query()->chunkById(
            200,
            function ($users) use (
                $amount,
                $reason,
                $bar,
                &$done,
                &$failed
            ) {
                foreach ($users as $user) {
                    try {
                        if (
                            CoinService::credit(
                                $user,
                                $amount,
                                CoinTransaction::SOURCE_ADMIN,
                                $reason
                            ) !== 0
                        ) {
                            $done++;
                        }
                    } catch (\RuntimeException $e) {
                        $failed++;
                    }

                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $this->newLine(2);

        // Частичный успех не считаем ошибкой команды: часть игроков
        // обработана, а сколько не удалось — видно в отчёте. Иначе
        // скрипты и CI падали бы из-за одного несостоятельного игрока.
        if ($failed > 0) {
            $this->warn("Начислено: {$done}, пропущено с ошибкой: {$failed}.");
        } else {
            $this->info("Начислено: {$done}.");
        }

        return self::SUCCESS;
    }

    /**
     * Оставляет daily_bonus + gift_in,
     * всё остальное списывает.
     */
    private function resetEveryone(): int
    {
        $total = User::where('apex_coins', '>', 0)->count();

        if ($total === 0) {
            $this->info('У игроков уже нулевой баланс.');
            return self::SUCCESS;
        }

        $this->warn("Будет обработано игроков: {$total}");
        $this->warn('Сохраняются: daily_bonus и gift_in.');
        $this->warn('Остальные ApexCoin будут списаны.');

        if (
            ! $this->option('force')
            && ! $this->confirm('Точно продолжить?', false)
        ) {
            $this->error('Отменено.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $done = 0;
        $skipped = 0;
        $failed = 0;

        User::query()
            ->where('apex_coins', '>', 0)
            ->chunkById(200, function ($users) use (
                $bar,
                &$done,
                &$skipped,
                &$failed
            ) {
                foreach ($users as $user) {
                    try {
                        $current = (int) $user->apex_coins;

                        $keep = (int) CoinTransaction::query()
                            ->where('user_id', $user->id)
                            ->whereIn('source', [
                                CoinTransaction::SOURCE_DAILY_BONUS,
                                CoinTransaction::SOURCE_GIFT_IN,
                            ])
                            ->where('amount', '>', 0)
                            ->sum('amount');

                        // Нельзя оставить больше текущего баланса
                        $keep = min($keep, $current);

                        $remove = $current - $keep;

                        if ($remove <= 0) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        CoinService::credit(
                            $user,
                            -$remove,
                            CoinTransaction::SOURCE_ADMIN,
                            $this->option('reason')
                                ?: 'Очистка баланса кроме daily bonus и подарков',
                            null,
                            [
                                'meta' => [
                                    'via' => 'artisan apex:coins',
                                    'action' => 'reset',
                                    'kept' => $keep,
                                    'removed' => $remove,
                                ],
                            ]
                        );

                        $done++;
                    } catch (\Throwable $e) {
                        $failed++;
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Списано у игроков: {$done}");
        $this->info("Без изменений: {$skipped}");

        if ($failed > 0) {
            $this->error("Ошибок: {$failed}");
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resetOne(string $target): int
    {
        if (! is_numeric($target)) {
            $this->error('ID игрока должен быть числом.');
            return self::FAILURE;
        }

        $user = User::find((int) $target);

        if (! $user) {
            $this->error("Игрок с ID {$target} не найден.");
            return self::FAILURE;
        }

        $current = (int) $user->apex_coins;

        $keep = (int) CoinTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('source', [
                CoinTransaction::SOURCE_DAILY_BONUS,
                CoinTransaction::SOURCE_GIFT_IN,
            ])
            ->where('amount', '>', 0)
            ->sum('amount');

        $keep = min($keep, $current);
        $remove = $current - $keep;

        if ($remove <= 0) {
            $this->info(
                "{$user->username}: баланс {$current} ApexCoin, "
                . 'списывать нечего.'
            );

            return self::SUCCESS;
        }

        $this->warn(
            "{$user->username}: баланс {$current}, "
            . "останется {$keep}, будет списано {$remove}."
        );

        if (
            ! $this->option('force')
            && ! $this->confirm('Продолжить?', false)
        ) {
            $this->error('Отменено.');
            return self::FAILURE;
        }

        try {
            CoinService::credit(
                $user,
                -$remove,
                CoinTransaction::SOURCE_ADMIN,
                $this->option('reason')
                    ?: 'Очистка баланса кроме daily bonus и подарков',
                null,
                [
                    'meta' => [
                        'via' => 'artisan apex:coins',
                        'action' => 'reset',
                        'kept' => $keep,
                        'removed' => $remove,
                    ],
                ]
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(
            "{$user->username}: было {$current}, "
            . "оставлено {$keep}, списано {$remove}."
        );

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

        if (! is_numeric($target)) {
            $this->error("ID игрока должен быть числом или 'all'.");
            return self::FAILURE;
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
