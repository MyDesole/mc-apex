<?php

namespace App\Http\Controllers;

use App\Models\TierTest;
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
            'tester_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(
            ($validated['tester_id'] ?? null) === $request->user()->id,
            422,
            'Нельзя записаться к себе.'
        );

        $test = TierTest::create([
            'user_id' => $request->user()->id,
            'tester_id' => $validated['tester_id'] ?? null,
            'mode' => $validated['mode'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        if ($test->tester_id) {
            User::find($test->tester_id)
                ->notify(new \App\Notifications\TierTestRequestNotification($test));
        }

        return response()->json([
            'tier_test' => $test->load(['tester:id,username,avatar']),
        ], 201);
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
            'result_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'result_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aspects' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($validated['status'] ?? null) === 'completed') {
            $validated['completed_at'] = now();
        }

        $tierTest->update($validated);

        if ($tierTest->status === 'completed' && $tierTest->result_tier) {
            $user = $tierTest->user;
            $user->tier = $tierTest->result_tier;
            $user->tier_score = $tierTest->result_score ?? 0;
            $user->save();

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
