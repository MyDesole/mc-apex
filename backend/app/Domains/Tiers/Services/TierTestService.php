<?php

namespace App\Domains\Tiers\Services;

use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Tiers\Resources\TierTestResource;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Wallet\Services\RewardService;

/**
 * Проведение тир-тестов: очередь, взятие в работу, результат.
 *
 * Расчёт тира и начисление наград — здесь, контроллер только принимает запрос.
 */
class TierTestService
{
    use AbortsWithMessage, GuardsActiveTierTest;

    public const PER_PAGE = 30;

    /** Порог суммы аспектов (максимум 100) для каждого тира. */
    private const TIER_THRESHOLDS = [
        'A' => 71,
        'B' => 56,
        'C' => 41,
        'D' => 21,
    ];

    private const QUEUE_RELATIONS = [
        'user:id,username,avatar,tier,tier_score',
        'user.clanMember.clan:id,tag,banner_color',
        'tester:id,username',
        'claimer:id,username',
    ];

    /* ----------------------------- Очередь ----------------------------- */

    /**
     * Очередь заявок. Приоритетные (купленный буст) идут вне очереди,
     * внутри группы — по времени создания.
     *
     * @param  array{status?: ?string, mode?: ?string, mine?: bool, free?: bool}  $filters
     */
    public function queue(array $filters, User $tester): LengthAwarePaginator
    {
        $query = TierTest::query()->with(self::QUEUE_RELATIONS);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', ['pending', 'in_progress']);
        }

        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        if (! empty($filters['mine'])) {
            $query->where('claimed_by', $tester->id);
        }

        if (! empty($filters['free'])) {
            $query->whereNull('claimed_by');
        }

        // Внутри одного приоритета сохраняется FIFO
        return $query
            ->orderByDesc('is_priority')
            ->orderByDesc('priority_weight')
            ->orderBy('created_at')
            ->paginate(self::PER_PAGE);
    }

    public function find(TierTest $tierTest): array
    {
        $tierTest->load([
            'user:id,username,avatar,tier,tier_score',
            // 'user.aspects' — аксессор, а не связь: загрузка падала с 500
            'user.clanMember.clan',
            'user.aspectPvp',
            'user.aspectBedwars',
            'tester:id,username',
            'claimer:id,username',
        ]);

        return [
            'tier_test' => (new TierTestResource($tierTest))->resolve(),
            'user' => $tierTest->user,
        ];
    }

    /* ----------------------------- Взятие в работу ----------------------------- */

    public function claim(TierTest $tierTest, User $tester): TierTest
    {
        if ($tierTest->status !== 'pending') {
            $this->abortUnprocessable('Заявка уже не в статусе ожидания.');
        }

        if ($tierTest->claimed_by && $tierTest->claimed_by !== $tester->id) {
            $this->abortUnprocessable('Заявку уже взял другой тестер.');
        }

        $tierTest->update([
            'tester_id' => $tester->id,
            'claimed_by' => $tester->id,
            'claimed_at' => now(),
            'status' => 'in_progress',
        ]);

        return $tierTest->fresh();
    }

    public function unclaim(TierTest $tierTest, User $tester): TierTest
    {
        $this->assertClaimedBy($tierTest, $tester);

        if ($tierTest->status !== 'in_progress') {
            $this->abortUnprocessable('Заявка уже не в работе.');
        }

        $tierTest->update([
            'claimed_by' => null,
            'claimed_at' => null,
            'tester_id' => null,
            'status' => 'pending',
        ]);

        return $tierTest->fresh();
    }

    /* ----------------------------- Результат ----------------------------- */

    /**
     * Завершить тест: сохранить аспекты, пересчитать тир, начислить награды.
     *
     * @param  array<string, mixed>  $scores  оценки по полям режима (+ notes)
     */
    public function complete(TierTest $tierTest, User $tester, array $scores): TierTest
    {
        $this->assertClaimedBy($tierTest, $tester);

        if ($tierTest->status !== 'in_progress') {
            $this->abortUnprocessable('Заявка не в работе.');
        }

        $score = $this->scoreFrom($tierTest->mode, $scores);
        $tier = $this->tierFor($score);

        DB::transaction(function () use ($tierTest, $scores, $score, $tier) {
            $tierTest->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_tier' => $tier,
                'result_score' => $score,
                'aspects' => $scores,
                'notes' => $scores['notes'] ?? null,
            ]);

            $this->storeAspects($tierTest->user_id, $tierTest->mode, $scores);

            $user = $tierTest->user->fresh();
            $user->recalcTierFromAspects();

            // ApexCoin за пройденный тест — идемпотентно по id теста
            RewardService::forTierTest($tierTest->fresh());
            AchievementService::check($user);
        });

        $finished = $tierTest->fresh();

        $finished->user?->notify(new \App\Domains\Tiers\Notifications\TierTestCompletedNotification($finished));

        return $finished;
    }

    /**
     * Провести тест вручную (админ): сразу с результатом и оценками.
     *
     * Пишет заявку со статусом completed, раскладывает аспекты по режиму,
     * пересчитывает тир, начисляет награды и уведомляет игрока.
     */
    public function conductManually(
        User $player,
        User $tester,
        string $mode,
        array $aspects,
        ?string $notes = null,
    ): TierTest {
        /*
         * Тест провели вручную — активная заявка больше не нужна.
         * Отменяем, а не завершаем: результата в ней нет, и статус
         * completed с пустым тиром испортил бы историю.
         */
        $this->activeTierTestFor($player->id)?->update([
            'status' => 'cancelled',
            'notes' => 'Тест проведён вручную администратором.',
        ]);

        $score = $this->scoreFrom($mode, $aspects);
        $tier = $this->tierFor($score);

        $test = DB::transaction(function () use ($player, $tester, $mode, $aspects, $notes, $score, $tier) {
            $test = TierTest::create([
                'user_id' => $player->id,
                'tester_id' => $tester->id,
                'mode' => $mode,
                'status' => 'completed',
                'completed_at' => now(),
                'result_tier' => $tier,
                'result_score' => $score,
                'aspects' => $aspects,
                'notes' => $notes,
            ]);

            $this->storeAspects($player->id, $mode, $aspects);

            $player->refresh();
            $player->recalcTierFromAspects();

            AchievementService::check($player);

            return $test;
        });

        $player->notify(new \App\Domains\Tiers\Notifications\TierTestCompletedNotification($test));

        return $test;
    }

    /**
     * Отмена заявки самим игроком.
     *
     * Игрок может отозвать заявку, пока её не взяли в работу. После этого
     * судьба теста — за тестером: отмену в работе делает только он.
     */
    public function cancelByPlayer(TierTest $tierTest, User $player, ?string $reason = null): TierTest
    {
        if ($tierTest->user_id !== $player->id) {
            abort(403, 'Это не ваша заявка.');
        }

        if ($tierTest->status !== 'pending') {
            $this->abortUnprocessable(
                $tierTest->status === 'in_progress'
                    ? 'Тест уже в работе. Отменить его может только тестер.'
                    : 'Эту заявку уже нельзя отменить.'
            );
        }

        $tierTest->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        return $tierTest->fresh();
    }

    public function cancel(TierTest $tierTest, User $tester, ?string $reason): TierTest
    {
        $this->assertClaimedBy($tierTest, $tester);

        $tierTest->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        return $tierTest->fresh();
    }

    /* ----------------------------- Статистика ----------------------------- */

    public function stats(User $tester): array
    {
        return [
            'total_completed' => TierTest::where('tester_id', $tester->id)
                ->where('status', 'completed')
                ->count(),
            'in_progress' => TierTest::where('claimed_by', $tester->id)
                ->where('status', 'in_progress')
                ->count(),
            'pending_total' => TierTest::where('status', 'pending')->count(),
            'today' => TierTest::where('tester_id', $tester->id)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    /* ----------------------------- Расчёт ----------------------------- */

    /**
     * Сумма аспектов режима. Максимум — 100 (пять полей по 20).
     */
    public function scoreFrom(string $mode, array $scores): int
    {
        $fields = $mode === 'pvp'
            ? ['block_placing', 'rotka', 'movement', 'aim', 'game_sense']
            : ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

        $sum = 0;

        foreach ($fields as $field) {
            $sum += (int) ($scores[$field] ?? 0);
        }

        return $sum;
    }

    /**
     * Тир по сумме аспектов.
     */
    public function tierFor(int $score): string
    {
        foreach (self::TIER_THRESHOLDS as $tier => $threshold) {
            if ($score >= $threshold) {
                return $tier;
            }
        }

        return 'E';
    }

    private function storeAspects(int $userId, string $mode, array $scores): void
    {
        // notes — не аспект, в таблицу аспектов он не пишется
        unset($scores['notes']);

        if ($mode === 'pvp') {
            PlayerAspectPvp::updateOrCreate(['user_id' => $userId], $scores);
        } else {
            PlayerAspectBedwars::updateOrCreate(['user_id' => $userId], $scores);
        }
    }

    private function assertClaimedBy(TierTest $tierTest, User $tester): void
    {
        abort_if($tierTest->claimed_by !== $tester->id, 403);
    }

    /**
     * Заявку видят только её автор и назначенный тестер.
     */
    public function assertCanAccess(TierTest $tierTest, User $user): void
    {
        abort_unless(
            $tierTest->user_id === $user->id || $tierTest->tester_id === $user->id,
            403,
            'Нет доступа к этой заявке.'
        );
    }
}
