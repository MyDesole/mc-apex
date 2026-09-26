<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayerAspect;
use App\Models\TierTest;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AspectController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'block_placing' => ['required', 'integer', 'min:0', 'max:20'],
            'rotka' => ['required', 'integer', 'min:0', 'max:20'],
            'movement' => ['required', 'integer', 'min:0', 'max:20'],
            'aim' => ['required', 'integer', 'min:0', 'max:20'],
            'game_sense' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        // Создаём/обновляем нужную модель аспектов
        if ($validated['mode'] === 'pvp') {
            \App\Models\PlayerAspectPvp::updateOrCreate(
                ['user_id' => $user->id],
                collect($validated)->only([
                    'block_placing', 'rotka', 'movement', 'aim', 'game_sense',
                ])->toArray()
            );
        } else {
            \App\Models\PlayerAspectBedwars::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'pvp' => $validated['block_placing'] ?? 0,
                    'game_sense' => $validated['game_sense'] ?? 0,
                    'bed_play' => $validated['rotka'] ?? 0,
                    'teamplay' => $validated['movement'] ?? 0,
                    'building' => $validated['aim'] ?? 0,
                ]
            );
        }

        $user = $user->fresh();
        $user->recalcTierFromAspects();
        AchievementService::check($user);

        return response()->json(['user' => $user->fresh()]);
    }

    public function conductTierTest(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'aspects' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['mode'] === 'pvp') {
            $aspects = $request->validate([
                'aspects.block_placing' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.rotka' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.movement' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.aim' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.game_sense' => ['required', 'integer', 'min:0', 'max:20'],
            ])['aspects'];

            $sum = array_sum($aspects);
        } else {
            $aspects = $request->validate([
                'aspects.pvp' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.game_sense' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.bed_play' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.teamplay' => ['required', 'integer', 'min:0', 'max:20'],
                'aspects.building' => ['required', 'integer', 'min:0', 'max:20'],
            ])['aspects'];

            $sum = array_sum($aspects);
        }

        $percent = $sum; // без *2

        $tier = match (true) {
            $percent >= 71 => 'A',
            $percent >= 56 => 'B',
            $percent >= 41 => 'C',
            $percent >= 21 => 'D',
            default => 'E',
        };

        DB::transaction(function () use ($user, $validated, $aspects, $percent, $tier, $request) {
            $test = TierTest::create([
                'user_id' => $user->id,
                'tester_id' => $request->user()->id,
                'mode' => $validated['mode'],
                'status' => 'completed',
                'completed_at' => now(),
                'result_tier' => $tier,
                'result_score' => $percent,
                'aspects' => $aspects,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($validated['mode'] === 'pvp') {
                \App\Models\PlayerAspectPvp::updateOrCreate(
                    ['user_id' => $user->id],
                    $aspects
                );
            } else {
                \App\Models\PlayerAspectBedwars::updateOrCreate(
                    ['user_id' => $user->id],
                    $aspects
                );
            }

            $user->refresh();
            $user->recalcTierFromAspects();
            AchievementService::check($user);

            $user->notify(new \App\Notifications\TierTestCompletedNotification($test));
        });

        return response()->json([
            'user' => $user->fresh(),
        ], 201);
    }
}
