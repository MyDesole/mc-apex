<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConductTierTestRequest;
use App\Http\Requests\Admin\UpdateUserAspectsRequest;
use App\Services\AchievementService;
use App\Services\TierTestService;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Аспекты игрока со стороны админки.
 *
 * Прямая правка аспектов и ручное проведение тир-теста.
 * Транзакция и награды — в TierTestService.
 */
class AspectController extends Controller
{
    public function __construct(
        private readonly TierTestService $tierTests,
    ) {
    }

    public function update(UpdateUserAspectsRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // ВНИМАНИЕ: у админского эндпоинта исторически свой маппинг полей для
        // bedwars — приходят имена pvp-набора, а ложатся в bedwars-колонки.
        // Так к нему обращается админка, поэтому поведение сохранено.
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

    public function conductTierTest(ConductTierTestRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $test = $this->tierTests->conductManually(
            player: $user,
            tester: $request->user(),
            mode: $validated['mode'],
            aspects: $validated['aspects'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json([
            'tier_test' => $test,
            'user' => $user->fresh(),
        ], 201);
    }
}
