<?php

namespace App\Http\Controllers;

use App\Models\TierTest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TierTestController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'contact_type' => ['required', 'in:discord,telegram'],
            'contact_value' => ['required', 'string', 'max:128'],
            'preferred_time' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

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
        $testers = \App\Models\User::whereIn('role', ['tester', 'admin'])->get();
        foreach ($testers as $tester) {
            $tester->notify(new \App\Notifications\TierTestRequestNotification($test));
        }

        return response()->json([
            'tier_test' => $test->load(['tester:id,username,avatar']),
        ], 201);
    }
    public function history(Request $request, User $user): JsonResponse
    {
        $tests = $user->tierTests()
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->get(['id', 'mode', 'result_tier', 'result_score', 'completed_at', 'aspects']);

        return response()->json(['history' => $tests]);
    }
    public function show(Request $request, TierTest $tierTest): JsonResponse
    {
        $this->authorizeAccess($request, $tierTest);

        return response()->json([
            'tier_test' => $tierTest->load(['user', 'tester']),
        ]);
    }

    public function update(Request $request, TierTest $tierTest): JsonResponse
    {
        $this->authorizeAccess($request, $tierTest);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'result_tier' => ['nullable', 'in:S+,S,A,B,C,D,E'],
            'result_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aspects' => ['nullable', 'array'],

            // валидация конкретных полей аспектов (универсальная для обоих режимов)
            'aspects.block_placing' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.rotka' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.movement' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.aim' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.game_sense' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.pvp' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.bed_play' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.teamplay' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.building' => ['nullable', 'integer', 'min:0', 'max:20'],

            'notes' => ['nullable', 'string'],
        ]);

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
                    \App\Models\PlayerAspectPvp::updateOrCreate(
                        ['user_id' => $user->id],
                        collect($aspects)->only([
                            'block_placing', 'rotka', 'movement', 'aim', 'game_sense',
                        ])->toArray()
                    );
                } else {
                    \App\Models\PlayerAspectBedwars::updateOrCreate(
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

            \App\Services\AchievementService::check($user);

            $user->notify(new \App\Notifications\TierTestCompletedNotification($tierTest));
        }

        return response()->json(['tier_test' => $tierTest->fresh()]);
    }

    private function authorizeAccess(Request $request, TierTest $tierTest): void
    {
        $userId = $request->user()->id;
        abort_unless(
            $tierTest->user_id === $userId || $tierTest->tester_id === $userId,
            403
        );
    }
}
