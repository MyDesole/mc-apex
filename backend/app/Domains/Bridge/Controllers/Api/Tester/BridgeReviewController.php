<?php

namespace App\Domains\Bridge\Controllers\Api\Tester;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Requests\AssignBridgeRankRequest;
use App\Domains\Bridge\Requests\ReviewBridgeTechniqueRequest;
use App\Domains\Bridge\Resources\BridgeRankResource;
use App\Domains\Bridge\Resources\BridgeSubmissionResource;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Players\Resources\UserCardResource;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Проверка бридж-заявок и выдача званий. Доступ только у роли
 * bridge_tester и админа, поэтому остальные игроки этого не видят.
 */
class BridgeReviewController extends Controller
{
    public function __construct(private readonly BridgeService $bridge)
    {
    }

    /** Заявки, ожидающие проверки. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => BridgeSubmissionResource::collection(
                $this->bridge->pending()
            )->resolve(),
        ]);
    }

    /** Подтвердить или отклонить заявку. */
    public function review(ReviewBridgeTechniqueRequest $request, UserBridgeTechnique $submission): JsonResponse
    {
        $validated = $request->validated();

        $result = $validated['confirm']
            ? $this->bridge->confirm($submission, $request->user(), [
                'stability' => (int) $validated['stability'],
                'speed' => (int) $validated['speed'],
                'difficulty' => (int) $validated['difficulty'],
                'score' => (int) $validated['score'],
                'notes' => $validated['notes'] ?? null,
                'variants' => $validated['variants'] ?? null,
            ])
            : $this->bridge->reject($submission, $request->user(), $validated['notes'] ?? null);

        return response()->json([
            'submission' => (new BridgeSubmissionResource(
                // reviewer нужен: в истории видно, кто проводил проверку
                $result->load('technique.variants', 'user', 'reviewer:id,username,avatar')
            ))->resolve(),
        ]);
    }

    /** История всех проверок: куратор видит и чужие проверки. */
    public function history(): JsonResponse
    {
        return response()->json([
            'data' => BridgeSubmissionResource::collection(
                $this->bridge->history()
            )->resolve(),
        ]);
    }

    /** Звания для выбора. */
    public function ranks(): JsonResponse
    {
        return response()->json([
            'data' => BridgeRankResource::collection($this->bridge->ranks())->resolve(),
        ]);
    }

    /** Бриджеры с подтверждёнными видами: здесь тестер меняет звания. */
    public function players(): JsonResponse
    {
        $rows = User::query()
            ->select('users.*')
            ->selectRaw($this->bridge->confirmedCountExpression() . ' AS bridge_techniques_count')
            ->selectRaw($this->bridge->aspectsTotalExpression() . ' AS bridge_aspects_total')
            ->with('bridgeRank:id,label,color')
            ->whereRaw($this->bridge->confirmedCountExpression() . ' > 0')
            ->excludeStaff()
            ->orderByDesc('bridge_techniques_count')
            ->orderByDesc('bridge_aspects_total')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (User $user) => [
                'user' => (new UserCardResource($user))->resolve(),
                'techniques_count' => (int) $user->bridge_techniques_count,
                'aspects_total' => (int) $user->bridge_aspects_total,
                'rank' => $user->bridgeRank
                    ? (new BridgeRankResource($user->bridgeRank))->resolve()
                    : null,
            ])->values(),
        ]);
    }

    /** Присвоить звание бриджера (rank_id = null снимает звание). */
    public function assignRank(AssignBridgeRankRequest $request, User $user): JsonResponse
    {
        $updated = $this->bridge->assignRank(
            player: $user,
            reviewer: $request->user(),
            rankId: $request->validated()['rank_id'] ?? null,
        );

        return response()->json([
            'user' => (new UserCardResource($updated->load('bridgeRank')))->resolve(),
            'rank' => $updated->bridgeRank
                ? (new BridgeRankResource($updated->bridgeRank))->resolve()
                : null,
        ]);
    }
}
