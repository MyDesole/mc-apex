<?php

namespace App\Domains\Bridge\Controllers\Api;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Requests\DeclareBridgeTechniqueRequest;
use App\Domains\Bridge\Resources\BridgeSubmissionResource;
use App\Domains\Bridge\Resources\BridgeTechniqueResource;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Бридж со стороны игрока: каталог видов, свои заявки и подача видео.
 *
 * Записи в очередь нет — вместо неё игрок отмечает вид и прикладывает
 * ролик, который проверит бридж-тестер.
 */
class BridgeController extends Controller
{
    public function __construct(private readonly BridgeService $bridge)
    {
    }

    /** Каталог видов бриджа с состоянием заявок текущего игрока. */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->bridge->forUser($request->user());

        return response()->json([
            'data' => $rows->map(fn (array $row) => [
                'technique' => (new BridgeTechniqueResource($row['technique']))->resolve(),
                'submission' => $row['submission']
                    ? (new BridgeSubmissionResource($row['submission']))->resolve()
                    : null,
            ])->values(),
            'summary' => $this->bridge->summary($request->user()),
        ]);
    }

    /** Подать вид: отметить, что умеешь, и приложить видео. */
    public function store(DeclareBridgeTechniqueRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $submission = $this->bridge->declare(
            user: $request->user(),
            techniqueId: (int) $validated['technique_id'],
            videoUrl: $validated['video_url'],
        );

        return response()->json([
            'submission' => (new BridgeSubmissionResource(
                $submission->load('technique', 'user')
            ))->resolve(),
        ], 201);
    }

    /** Убрать свою заявку (подтверждённую убрать нельзя). */
    public function destroy(Request $request, UserBridgeTechnique $submission): JsonResponse
    {
        $this->bridge->withdraw($request->user(), $submission);

        return response()->json(['ok' => true]);
    }

    /** Подтверждённые виды игрока — публичный профиль бриджера. */
    public function user(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'data' => BridgeSubmissionResource::collection(
                $this->bridge->confirmedForUser($user)->load('technique')
            )->resolve(),
            'summary' => $this->bridge->summary($user),
            'rank' => $this->bridge->rankOf($user),
        ]);
    }
}
