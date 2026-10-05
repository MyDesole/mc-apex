<?php

namespace App\Domains\Bridge\Controllers\Api\Tester;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Requests\ReviewBridgeTechniqueRequest;
use App\Domains\Bridge\Resources\BridgeSubmissionResource;
use App\Domains\Bridge\Services\BridgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Проверка бридж-заявок. Доступ только у роли bridge_tester и админа,
 * поэтому остальные игроки этого раздела не видят.
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
            ])
            : $this->bridge->reject($submission, $request->user(), $validated['notes'] ?? null);

        return response()->json([
            'submission' => (new BridgeSubmissionResource(
                $result->load('technique', 'user')
            ))->resolve(),
        ]);
    }
}
