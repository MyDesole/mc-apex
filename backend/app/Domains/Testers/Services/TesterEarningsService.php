<?php

namespace App\Domains\Testers\Services;

use App\Domains\Testers\Models\TesterEarning;
use App\Domains\Testers\Models\TesterPayout;
use App\Domains\Testers\Support\TesterRates;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Заработок тестера: начисления за тесты и запросы на выплату.
 *
 * Деньги сайт не переводит сам — админ платит вручную и отмечает выплату.
 * Поэтому «долг сайта» = начислено − выплачено − уже запрошено.
 */
class TesterEarningsService
{
    /** Начислить за завершённый тест (идемпотентно по id теста). */
    public function accrue(TierTest $test): ?TesterEarning
    {
        if ($test->status !== 'completed' || ! $test->tester_id) {
            return null;
        }

        $existing = TesterEarning::where('tier_test_id', $test->id)->first();

        if ($existing) {
            return $existing;
        }

        $reward = TesterRates::reward($test->result_tier);

        return TesterEarning::create([
            'user_id' => $test->tester_id,
            'tier_test_id' => $test->id,
            'tier' => $test->result_tier,
            'rub' => $reward['rub'],
            'coins' => $reward['coins'],
        ]);
    }

    /**
     * Сводка: за месяц, выплачено, в ожидании и сколько можно запросить.
     *
     * @return array<string, int|float>
     */
    public function summary(User $tester): array
    {
        $monthStart = now()->startOfMonth();

        $earnedRub = (int) TesterEarning::where('user_id', $tester->id)->sum('rub');
        $earnedCoins = (int) TesterEarning::where('user_id', $tester->id)->sum('coins');

        $monthRub = (int) TesterEarning::where('user_id', $tester->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('rub');
        $monthCoins = (int) TesterEarning::where('user_id', $tester->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('coins');

        $paidRub = (int) TesterPayout::where('user_id', $tester->id)
            ->where('status', TesterPayout::PAID)
            ->sum('rub');
        $paidCoins = (int) TesterPayout::where('user_id', $tester->id)
            ->where('status', TesterPayout::PAID)
            ->sum('coins');

        $pendingRub = (int) TesterPayout::where('user_id', $tester->id)
            ->where('status', TesterPayout::PENDING)
            ->sum('rub');
        $pendingCoins = (int) TesterPayout::where('user_id', $tester->id)
            ->where('status', TesterPayout::PENDING)
            ->sum('coins');

        return [
            'month_rub' => $monthRub,
            'month_coins' => $monthCoins,
            'earned_rub' => $earnedRub,
            'earned_coins' => $earnedCoins,
            'paid_rub' => $paidRub,
            'paid_coins' => $paidCoins,
            'pending_rub' => $pendingRub,
            'pending_coins' => $pendingCoins,
            // Доступно к запросу: начислено минус всё, что уже запрошено или выплачено
            'available_rub' => max(0, $earnedRub - $paidRub - $pendingRub),
            'available_coins' => max(0, $earnedCoins - $paidCoins - $pendingCoins),
            'tests' => (int) TesterEarning::where('user_id', $tester->id)->count(),
        ];
    }

    /** История начислений с ником игрока — для таблицы в кабинете. */
    public function history(User $tester, int $limit = 40): Collection
    {
        return TierTest::query()
            ->with('user:id,username')
            ->where('tester_id', $tester->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(function (TierTest $test) {
                $earning = TesterEarning::where('tier_test_id', $test->id)->first();

                return [
                    'id' => $test->id,
                    'completed_at' => $test->completed_at?->toIso8601String(),
                    'player' => $test->user?->username,
                    'tier' => $test->result_tier,
                    'rub' => (int) ($earning->rub ?? 0),
                    'coins' => (int) ($earning->coins ?? 0),
                ];
            });
    }

    /** Запросить выплату всего доступного. */
    public function requestPayout(User $tester): TesterPayout
    {
        $summary = $this->summary($tester);

        if ($summary['available_rub'] <= 0 && $summary['available_coins'] <= 0) {
            throw new RuntimeException('Пока нечего выводить.');
        }

        return DB::transaction(function () use ($tester, $summary) {
            // Между проверкой и созданием мог появиться другой запрос
            $pending = TesterPayout::where('user_id', $tester->id)
                ->where('status', TesterPayout::PENDING)
                ->exists();

            if ($pending) {
                throw new RuntimeException('Предыдущий запрос ещё не рассмотрен.');
            }

            return TesterPayout::create([
                'user_id' => $tester->id,
                'rub' => $summary['available_rub'],
                'coins' => $summary['available_coins'],
                'status' => TesterPayout::PENDING,
            ]);
        });
    }

    public function payouts(User $tester): Collection
    {
        return TesterPayout::where('user_id', $tester->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    /** Все запросы выплат — админу. */
    public function allPayouts(?string $status = null): Collection
    {
        return TesterPayout::query()
            ->with('tester:id,username,avatar')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /** Решение по выплате: выплачено или отклонено. */
    public function decide(TesterPayout $payout, User $admin, string $status, ?string $comment = null): TesterPayout
    {
        if (! in_array($status, [TesterPayout::PAID, TesterPayout::REJECTED], true)) {
            throw new RuntimeException('Неизвестный статус выплаты.');
        }

        if ($payout->status !== TesterPayout::PENDING) {
            throw new RuntimeException('Этот запрос уже рассмотрен.');
        }

        $payout->update([
            'status' => $status,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'comment' => $comment,
        ]);

        if ($status === TesterPayout::PAID && $payout->coins > 0) {
            // Монеты уходят на кошелёк тестера через общий сервис
            \App\Domains\Wallet\Services\CoinService::credit(
                $payout->tester,
                (int) $payout->coins,
                'tester_payout',
                'Выплата за тесты',
                'tester-payout-' . $payout->id,
            );
        }

        return $payout->fresh(['tester:id,username']);
    }
}
