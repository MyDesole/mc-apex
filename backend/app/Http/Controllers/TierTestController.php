<?php

namespace App\Http\Controllers;

use App\Models\TierTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TierTestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tests = TierTest::where('user_id', $request->user()->id)
            ->with(['tester:id,username,avatar'])
            ->latest()
            ->paginate(20);

        return response()->json($tests);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $test = TierTest::create([
            'user_id' => $request->user()->id,
            'mode' => $validated['mode'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['tier_test' => $test], 201);
    }

    public function show(TierTest $tierTest): JsonResponse
    {
        $this->authorizeAccess($tierTest);

        return response()->json([
            'tier_test' => $tierTest->load(['user', 'tester']),
        ]);
    }

    public function update(Request $request, TierTest $tierTest): JsonResponse
    {
        $this->authorizeAccess($tierTest);

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

        return response()->json(['tier_test' => $tierTest]);
    }

    private function authorizeAccess(TierTest $tierTest): void
    {
        $userId = auth()->id();

        abort_unless(
            $tierTest->user_id === $userId || $tierTest->tester_id === $userId,
            403
        );
    }
}
