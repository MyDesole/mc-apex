<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayerAspect;
use App\Models\TierTest;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AspectController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'block_placing' => ['required', 'integer', 'min:0', 'max:10'],
            'rotka' => ['required', 'integer', 'min:0', 'max:10'],
            'movement' => ['required', 'integer', 'min:0', 'max:10'],
            'building' => ['required', 'integer', 'min:0', 'max:10'],
            'ppl' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $aspect = PlayerAspect::updateOrCreate(
            ['user_id' => $user->id, 'mode' => $validated['mode']],
            $validated
        );

        // пересчитываем тир
        $user->tier_score = $aspect->percent();
        $user->tier = $aspect->tier();
        $user->save();

        AchievementService::check($user);

        return response()->json(['aspect' => $aspect, 'user' => $user->fresh()]);
    }

    public function conductTierTest(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'block_placing' => ['required', 'integer', 'min:0', 'max:10'],
            'rotka' => ['required', 'integer', 'min:0', 'max:10'],
            'movement' => ['required', 'integer', 'min:0', 'max:10'],
            'building' => ['required', 'integer', 'min:0', 'max:10'],
            'ppl' => ['required', 'integer', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sum = $validated['block_placing'] + $validated['rotka']
            + $validated['movement'] + $validated['building'] + $validated['ppl'];
        $percent = $sum * 2;

        $tier = match (true) {
            $percent >= 90 => 'S',
            $percent >= 80 => 'A',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C',
            $percent >= 50 => 'D',
            default => 'E',
        };

        // создаём запись тир-теста
        $test = TierTest::create([
            'user_id' => $user->id,
            'tester_id' => $request->user()->id,
            'mode' => $validated['mode'],
            'status' => 'completed',
            'completed_at' => now(),
            'result_tier' => $tier,
            'result_score' => $percent,
            'aspects' => [
                'block_placing' => $validated['block_placing'],
                'rotka' => $validated['rotka'],
                'movement' => $validated['movement'],
                'building' => $validated['building'],
                'ppl' => $validated['ppl'],
            ],
            'notes' => $validated['notes'] ?? null,
        ]);

        // обновляем аспекты и тир юзера
        PlayerAspect::updateOrCreate(
            ['user_id' => $user->id, 'mode' => $validated['mode']],
            $validated
        );

        $user->tier = $tier;
        $user->tier_score = $percent;
        $user->save();

        AchievementService::check($user);
        $user->notify(new \App\Notifications\TierTestCompletedNotification($test));

        return response()->json([
            'tier_test' => $test,
            'user' => $user->fresh(),
        ], 201);
    }
}
