<?php

namespace App\Domains\Testers\Controllers\Api\Tester;

use App\Domains\Testers\Services\TesterEarningsService;
use App\Domains\Testers\Services\TesterScheduleService;
use App\Domains\Testers\Support\TesterRates;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Services\TierTestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Сводка кабинета: профиль, статистика, слоты, заявки в работе, деньги.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly TesterScheduleService $schedule,
        private readonly TesterEarningsService $earnings,
        private readonly TierTestService $tiers,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->schedule->profile($user);

        $completed = TierTest::where('tester_id', $user->id)->where('status', 'completed');
        $count = (clone $completed)->count();
        $avgScore = round((float) (clone $completed)->avg('result_score'), 1);

        $mine = TierTest::with('user:id,username')
            ->where('claimed_by', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (TierTest $test) => [
                'id' => $test->id,
                'player' => $test->user?->username,
                'mode' => $test->mode,
                'status' => $test->status,
                'scheduled_at' => $test->scheduled_at?->toIso8601String(),
                'expected_tier' => $test->expected_tier,
                'is_priority' => (bool) $test->is_priority,
            ]);

        return response()->json([
            'profile' => [
                'blocked' => $this->schedule->isBlocked($user),
                'blocked_until' => $profile->blocked_until?->toIso8601String(),
                'blocked_reason' => $profile->blocked_reason,
            ],
            'stats' => [
                'completed' => $count,
                'avg_score' => $avgScore,
                'avg_tier' => $count > 0 ? $this->tiers->tierFor((int) round($avgScore)) : null,
                'cancelled' => TierTest::where('claimed_by', $user->id)->where('status', 'cancelled')->count(),
                'tiers' => (clone $completed)
                    ->selectRaw('result_tier as tier, COUNT(*) as total')
                    ->groupBy('result_tier')
                    ->pluck('total', 'tier'),
            ],
            'rates' => TesterRates::settings(),
            // Сколько заявок ждёт в общей очереди
            'queue' => TierTest::whereNull('claimed_by')->where('status', 'pending')->count(),
            'slots' => $this->schedule->window($user),
            'tests' => $mine,
            'earnings' => $this->earnings->summary($user),
            'history' => $this->earnings->history($user),
            'payouts' => $this->earnings->payouts($user),
        ]);
    }
}
