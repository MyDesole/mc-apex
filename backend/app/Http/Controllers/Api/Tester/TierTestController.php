<?php

namespace App\Http\Controllers\Api\Tester;

use App\Http\Controllers\Controller;
use App\Models\PlayerAspect;
use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\TierTest;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TierTestController extends Controller
{
    /**
     * Список заявок на тир-тесты.
     * По умолчанию — pending, можно фильтровать по статусу.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TierTest::query()
            ->with([
                'user:id,username,avatar,tier,tier_score',
                'user.clanMember.clan:id,tag,banner_color',
                'tester:id,username',
                'claimer:id,username',
            ]);

        // Фильтр по статусу
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['pending', 'in_progress']);
        }

        // Фильтр по режиму
        if ($mode = $request->query('mode')) {
            $query->where('mode', $mode);
        }

        // "Мои заявки" — только те, что я взял
        if ($request->query('mine') === '1') {
            $query->where('claimed_by', $request->user()->id);
        }

        // "Свободные" — никем не взятые
        if ($request->query('free') === '1') {
            $query->whereNull('claimed_by');
        }

        $tests = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($tests);
    }

    /**
     * Детали заявки.
     */
    public function show(Request $request, TierTest $tierTest): JsonResponse
    {
        $tierTest->load([
            'user:id,username,avatar,tier,tier_score',
            'user.clanMember.clan',
            'user.aspects',
            'tester:id,username',
            'claimer:id,username',
        ]);

        return response()->json([
            'tier_test' => $tierTest,
            'user' => $tierTest->user,
        ]);
    }

    /**
     * Взять заявку в работу.
     */
    public function claim(Request $request, TierTest $tierTest): JsonResponse
    {
        if ($tierTest->status !== 'pending') {
            return response()->json([
                'message' => 'Заявка уже не в статусе ожидания.',
            ], 422);
        }

        if ($tierTest->claimed_by && $tierTest->claimed_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Заявку уже взял другой тестер.',
            ], 422);
        }

        $tierTest->update([
            'tester_id' => $request->user()->id,
            'claimed_by' => $request->user()->id,
            'claimed_at' => now(),
            'status' => 'in_progress',
        ]);

        return response()->json(['tier_test' => $tierTest->fresh()]);
    }

    /**
     * Отказаться от заявки (вернуть в pending).
     */
    public function unclaim(Request $request, TierTest $tierTest): JsonResponse
    {
        abort_if($tierTest->claimed_by !== $request->user()->id, 403);
        abort_if($tierTest->status !== 'in_progress', 422, 'Заявка уже не в работе.');

        $tierTest->update([
            'claimed_by' => null,
            'claimed_at' => null,
            'tester_id' => null,
            'status' => 'pending',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Провести тир-тест — внести оценки, рассчитать тир.
     */
    public function complete(Request $request, TierTest $tierTest): JsonResponse
    {
        abort_if($tierTest->claimed_by !== $request->user()->id, 403);
        abort_if($tierTest->status !== 'in_progress', 422, 'Заявка не в работе.');

        if ($tierTest->mode === 'pvp') {
            $validated = $request->validate([
                'block_placing' => ['required', 'integer', 'min:0', 'max:20'],
                'rotka' => ['required', 'integer', 'min:0', 'max:20'],
                'movement' => ['required', 'integer', 'min:0', 'max:20'],
                'aim' => ['required', 'integer', 'min:0', 'max:20'],
                'game_sense' => ['required', 'integer', 'min:0', 'max:20'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $sum = $validated['block_placing'] + $validated['rotka']
                + $validated['movement'] + $validated['aim'] + $validated['game_sense'];
        } else {
            $validated = $request->validate([
                'pvp' => ['required', 'integer', 'min:0', 'max:20'],
                'game_sense' => ['required', 'integer', 'min:0', 'max:20'],
                'bed_play' => ['required', 'integer', 'min:0', 'max:20'],
                'teamplay' => ['required', 'integer', 'min:0', 'max:20'],
                'building' => ['required', 'integer', 'min:0', 'max:20'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $sum = $validated['pvp'] + $validated['game_sense']
                + $validated['bed_play'] + $validated['teamplay'] + $validated['building'];
        }

        $percent = $sum; // без *2 — максимум 100

        $tier = match (true) {
            $percent >= 71 => 'A',
            $percent >= 56 => 'B',
            $percent >= 41 => 'C',
            $percent >= 21 => 'D',
            default => 'E',
        };

        DB::transaction(function () use ($tierTest, $validated, $percent, $tier) {
            $tierTest->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_tier' => $tier,
                'result_score' => $percent,
                'aspects' => $validated,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($tierTest->mode === 'pvp') {
                PlayerAspectPvp::updateOrCreate(
                    ['user_id' => $tierTest->user_id],
                    $validated
                );
            } else {
                PlayerAspectBedwars::updateOrCreate(
                    ['user_id' => $tierTest->user_id],
                    $validated
                );
            }

            $user = $tierTest->user->fresh();
            $user->recalcTierFromAspects();
            AchievementService::check($user);
        });

        $tierTest->user->notify(new \App\Notifications\TierTestCompletedNotification($tierTest->fresh()));

        return response()->json([
            'tier_test' => $tierTest->fresh(),
            'user' => $tierTest->user->fresh(),
        ]);
    }
    /**
     * Отменить заявку (тестер не смог провести).
     */
    public function cancel(Request $request, TierTest $tierTest): JsonResponse
    {
        abort_if($tierTest->claimed_by !== $request->user()->id, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tierTest->update([
            'status' => 'cancelled',
            'notes' => $validated['reason'] ?? null,
        ]);

        return response()->json(['tier_test' => $tierTest->fresh()]);
    }

    /**
     * Статистика тестера.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'total_completed' => TierTest::where('tester_id', $userId)->where('status', 'completed')->count(),
            'in_progress' => TierTest::where('claimed_by', $userId)->where('status', 'in_progress')->count(),
            'pending_total' => TierTest::where('status', 'pending')->count(),
            'today' => TierTest::where('tester_id', $userId)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ]);
    }
}
