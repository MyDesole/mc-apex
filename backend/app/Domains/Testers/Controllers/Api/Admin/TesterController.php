<?php

namespace App\Domains\Testers\Controllers\Api\Admin;

use App\Domains\Testers\Models\TesterEarning;
use App\Domains\Testers\Models\TesterPayout;
use App\Domains\Testers\Models\TesterProfile;
use App\Domains\Testers\Services\TesterEarningsService;
use App\Domains\Testers\Services\TesterScheduleService;
use App\Domains\Testers\Support\TesterRates;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Управление тестерами: список, глобальный календарь, все заявки,
 * экономика, выплаты и блокировка доступа.
 */
class TesterController extends Controller
{
    public function __construct(
        private readonly TesterScheduleService $schedule,
        private readonly TesterEarningsService $earnings,
    ) {
    }

    /** Тестеры со сводкой: тесты, средний балл, заработок, блокировка. */
    public function index(): JsonResponse
    {
        $testers = User::whereIn('role', ['tester', 'admin'])
            ->orderBy('username')
            ->get(['id', 'username', 'avatar', 'role']);

        $completed = TierTest::where('status', 'completed')->whereNotNull('tester_id')
            ->selectRaw('tester_id, COUNT(*) as total, AVG(result_score) as avg_score')
            ->groupBy('tester_id')->get()->keyBy('tester_id');

        $cancelled = TierTest::where('status', 'cancelled')->whereNotNull('claimed_by')
            ->selectRaw('claimed_by, COUNT(*) as total')
            ->groupBy('claimed_by')->get()->keyBy('claimed_by');

        $earned = TesterEarning::selectRaw('user_id, SUM(rub) as rub, SUM(coins) as coins')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $paid = TesterPayout::where('status', TesterPayout::PAID)
            ->selectRaw('user_id, SUM(rub) as rub, SUM(coins) as coins')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $pending = TesterPayout::where('status', TesterPayout::PENDING)
            ->selectRaw('user_id, SUM(rub) as rub, SUM(coins) as coins')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $profiles = TesterProfile::all()->keyBy('user_id');

        return response()->json([
            'testers' => $testers->map(function (User $tester) use ($completed, $cancelled, $earned, $paid, $pending, $profiles) {
                $profile = $profiles[$tester->id] ?? null;

                return [
                    'id' => $tester->id,
                    'username' => $tester->username,
                    'role' => $tester->role,
                    'completed' => (int) ($completed[$tester->id]->total ?? 0),
                    'avg_score' => round((float) ($completed[$tester->id]->avg_score ?? 0), 1),
                    'cancelled' => (int) ($cancelled[$tester->id]->total ?? 0),
                    'earned_rub' => (int) ($earned[$tester->id]->rub ?? 0),
                    'earned_coins' => (int) ($earned[$tester->id]->coins ?? 0),
                    'paid_rub' => (int) ($paid[$tester->id]->rub ?? 0),
                    'pending_rub' => (int) ($pending[$tester->id]->rub ?? 0),
                    'blocked_until' => $profile?->blocked_until?->toIso8601String(),
                    'blocked_reason' => $profile?->blocked_reason,
                ];
            })->values(),
        ]);
    }

    /** Глобальный календарь: кто, кого и когда тестирует. */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'slots' => $this->schedule->calendar($data['from'] ?? null, $data['to'] ?? null),
        ]);
    }

    /** Все заявки: игрок, тестер, время, статус. */
    public function tests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:60'],
        ]);

        $query = TierTest::query()
            ->with(['user:id,username', 'tester:id,username'])
            ->orderByDesc('created_at');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['search'])) {
            $search = '%' . $data['search'] . '%';

            $query->whereHas('user', fn ($q) => $q->where('username', 'like', $search));
        }

        return response()->json($query->paginate(30));
    }

    /** Экономика: ставки и множители по тирам. */
    public function settings(): JsonResponse
    {
        return response()->json(TesterRates::settings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pay_rub' => ['required', 'integer', 'min:0', 'max:100000'],
            'pay_coins' => ['required', 'integer', 'min:0', 'max:1000000'],
            'tier_multipliers' => ['required', 'array'],
            'tier_multipliers.*' => ['numeric', 'min:0', 'max:10'],
        ]);

        TesterRates::save(
            (int) $data['pay_rub'],
            (int) $data['pay_coins'],
            (array) $data['tier_multipliers'],
        );

        return response()->json(TesterRates::settings());
    }

    /** Запросы на выплату. */
    public function payouts(Request $request): JsonResponse
    {
        $status = $request->query('status');

        return response()->json([
            'payouts' => $this->earnings->allPayouts(is_string($status) ? $status : null),
        ]);
    }

    public function pay(Request $request, TesterPayout $payout): JsonResponse
    {
        return $this->decide($request, $payout, TesterPayout::PAID);
    }

    public function reject(Request $request, TesterPayout $payout): JsonResponse
    {
        return $this->decide($request, $payout, TesterPayout::REJECTED);
    }

    private function decide(Request $request, TesterPayout $payout, string $status): JsonResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:200']]);

        try {
            $decided = $this->earnings->decide($payout, $request->user(), $status, $data['comment'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['payout' => $decided]);
    }

    /** Закрыть календарь тестеру (например, ушёл в афк). */
    public function block(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'until' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        $profile = $this->schedule->block($user, (string) $data['until'], $data['reason'] ?? null);

        return response()->json(['profile' => $profile]);
    }

    public function unblock(User $user): JsonResponse
    {
        $profile = $this->schedule->block($user, null, null);

        return response()->json(['profile' => $profile]);
    }
}
