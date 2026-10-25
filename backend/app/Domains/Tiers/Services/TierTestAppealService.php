<?php

namespace App\Domains\Tiers\Services;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestAppeal;
use App\Domains\Tiers\Notifications\TierTestAppealResolvedNotification;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\DB;

/**
 * Апелляции на результат тир-теста.
 *
 * Игрок оспаривает свой последний тест в течение WINDOW_DAYS дней.
 * Модератор отклоняет апелляцию или пересматривает оценки — тогда
 * появляется новый тест в истории, а тир пересчитывается.
 */
class TierTestAppealService
{
    use AbortsWithMessage;

    public const WINDOW_DAYS = 14;

    public function __construct(private TierTestService $tierTests) {}

    public function submit(TierTest $tierTest, User $player, string $reason): TierTestAppeal
    {
        if ($tierTest->user_id !== $player->id) {
            $this->abortForbidden('Оспорить можно только свой тест.');
        }

        $this->assertAppealable($tierTest);

        return TierTestAppeal::create([
            'tier_test_id' => $tierTest->id,
            'user_id' => $player->id,
            'reason' => $reason,
            'status' => TierTestAppeal::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array<string, int>|null  $aspects  новые оценки — только при одобрении
     */
    public function resolve(
        TierTestAppeal $appeal,
        User $reviewer,
        string $decision,
        ?string $resolution,
        ?array $aspects = null,
    ): TierTestAppeal {
        if ($appeal->status !== TierTestAppeal::STATUS_PENDING) {
            $this->abortUnprocessable('Апелляция уже рассмотрена.');
        }

        $test = $appeal->tierTest;

        // Свой же тест не пересматривают — нужен второй взгляд
        if ($test->tester_id === $reviewer->id || $test->claimed_by === $reviewer->id) {
            $this->abortForbidden('Тест проводили вы — апелляцию рассматривает другой модератор.');
        }

        DB::transaction(function () use ($appeal, $reviewer, $decision, $resolution, $aspects, $test) {
            $revised = null;

            if ($decision === TierTestAppeal::STATUS_APPROVED) {
                $revised = $this->tierTests->conductManually(
                    $appeal->user,
                    $reviewer,
                    $test->mode,
                    $aspects ?? [],
                    "Пересмотр по апелляции на тест #{$test->id}",
                    notify: false,
                    cancelActive: false,
                );
            }

            $appeal->update([
                'status' => $decision,
                'reviewer_id' => $reviewer->id,
                'resolution' => $resolution,
                'revised_test_id' => $revised?->id,
                'resolved_at' => now(),
            ]);
        });

        $appeal->refresh()->load('revisedTest');
        $appeal->user?->notify(new TierTestAppealResolvedNotification($appeal));

        return $appeal;
    }

    /**
     * Можно ли оспорить тест: завершён, свежий, последний в своём режиме,
     * апелляции на него ещё не было.
     */
    public function appealable(TierTest $tierTest): bool
    {
        return $this->appealError($tierTest) === null;
    }

    private function assertAppealable(TierTest $tierTest): void
    {
        $error = $this->appealError($tierTest);

        if ($error !== null) {
            $this->abortUnprocessable($error);
        }
    }

    private function appealError(TierTest $tierTest): ?string
    {
        if ($tierTest->status !== 'completed' || ! $tierTest->completed_at) {
            return 'Оспорить можно только завершённый тест.';
        }

        if ($tierTest->completed_at->lt(now()->subDays(self::WINDOW_DAYS))) {
            return 'Срок апелляции — '.self::WINDOW_DAYS.' дней после теста.';
        }

        if (TierTestAppeal::where('tier_test_id', $tierTest->id)->exists()) {
            return 'Апелляция на этот тест уже подана.';
        }

        $newer = TierTest::where('user_id', $tierTest->user_id)
            ->where('mode', $tierTest->mode)
            ->where('status', 'completed')
            ->where('completed_at', '>', $tierTest->completed_at)
            ->exists();

        return $newer ? 'Оспорить можно только последний тест в режиме.' : null;
    }
}
