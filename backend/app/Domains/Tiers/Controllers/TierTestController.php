<?php

namespace App\Domains\Tiers\Controllers;

use App\Domains\Tiers\Requests\TierTest\CreateTierTestRequest;
use App\Domains\Tiers\Requests\TierTest\UpdateTierTestRequest;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Domains\Tiers\Services\TierTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TierTestController extends Controller
{
    public function __construct(
        private readonly TierTestService $tierTests,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Мои заявки (как игрока)
        $myTests = TierTest::where('user_id', $userId)
            ->with(['tester:id,username,avatar,tier'])
            ->latest()
            ->get();

        // Заявки, где я тестер
        $asTester = TierTest::where('tester_id', $userId)
            ->with(['user:id,username,avatar,tier'])
            ->latest()
            ->get();

        return response()->json([
            'my_tests' => $myTests,
            'as_tester' => $asTester,
        ]);
    }

    public function store(CreateTierTestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Одна активная заявка на игрока: иначе в очереди появляются дубли
        $this->tierTests->assertNoActiveTierTest($request->user());

        $test = TierTest::create([
            'user_id' => $request->user()->id,
            'mode' => $validated['mode'],
            'contact_type' => $validated['contact_type'],
            'contact_value' => $validated['contact_value'],
            'preferred_time' => $validated['preferred_time'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        // уведомляем тестеров и админов
        $testers = \App\Domains\Users\Models\User::whereIn('role', ['tester', 'admin'])->get();
        foreach ($testers as $tester) {
            $tester->notify(new \App\Domains\Tiers\Notifications\TierTestRequestNotification($test));
        }

        return response()->json([
            'tier_test' => $test->load(['tester:id,username,avatar']),
        ], 201);
    }
    public function history(Request $request, User $user): JsonResponse
    {
        $tests = $user->tierTests()
            ->where('status', 'completed')
            ->with('tester:id,username,avatar')
            ->orderBy('completed_at')
            ->get([
                'id', 'mode', 'result_tier', 'result_score', 'completed_at',
                'aspects', 'tester_id',
            ]);

        return response()->json(['history' => $tests]);
    }
    public function show(Request $request, TierTest $tierTest): JsonResponse
    {
        $this->tierTests->assertCanAccess($tierTest, $request->user());

        return response()->json([
            'tier_test' => $tierTest->load(['user', 'tester']),
        ]);
    }

    public function update(UpdateTierTestRequest $request, TierTest $tierTest): JsonResponse
    {
        $this->tierTests->assertCanAccess($tierTest, $request->user());

        $validated = $request->validated();

        if (($validated['status'] ?? null) === 'completed') {
            $validated['completed_at'] = now();
        }

        $tierTest->update($validated);

        if ($tierTest->status === 'completed' && $tierTest->result_tier) {
            $user = $tierTest->user;

            // Если в aspects пришли оценки — обновляем модель аспектов
            if (!empty($validated['aspects'])) {
                $aspects = $validated['aspects'];

                if ($tierTest->mode === 'pvp') {
                    \App\Domains\Players\Models\PlayerAspectPvp::updateOrCreate(
                        ['user_id' => $user->id],
                        collect($aspects)->only([
                            'block_placing', 'rotka', 'movement', 'aim', 'game_sense',
                        ])->toArray()
                    );
                } else {
                    \App\Domains\Players\Models\PlayerAspectBedwars::updateOrCreate(
                        ['user_id' => $user->id],
                        collect($aspects)->only([
                            'pvp', 'game_sense', 'bed_play', 'teamplay', 'building',
                        ])->toArray()
                    );
                }

                $user = $user->fresh();
            }

            // tier_score всегда обновляем
            $user->tier_score = $tierTest->result_score ?? $user->tier_score;

            // S/S+ не понижаем автоматически
            if (!in_array($user->tier, ['S', 'S+'], true)) {
                $user->tier = $tierTest->result_tier;
            }

            $user->save();

            \App\Domains\Achievements\Services\AchievementService::check($user);

            // ApexCoin за пройденный тир-тест (идемпотентно по id теста)
            \App\Domains\Wallet\Services\RewardService::forTierTest($tierTest->fresh());

            $user->notify(new \App\Domains\Tiers\Notifications\TierTestCompletedNotification($tierTest));
        }

        return response()->json(['tier_test' => $tierTest->fresh()]);
    }

}
