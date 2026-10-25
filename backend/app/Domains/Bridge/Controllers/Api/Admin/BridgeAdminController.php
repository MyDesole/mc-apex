<?php

namespace App\Domains\Bridge\Controllers\Api\Admin;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Resources\BridgeRankResource;
use App\Domains\Bridge\Resources\BridgeSubmissionResource;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Bridge\Services\BridgeVideoService;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Players\Services\RatingService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Бридж игрока в досье админа: исправить завышенные оценки тестера,
 * сменить статус заявки, удалить её и поменять звание.
 * Все правки пишутся в журнал модерации и сразу пересчитывают рейтинг.
 */
class BridgeAdminController extends Controller
{
    public function __construct(private readonly BridgeService $bridge)
    {
    }

    /** Все заявки игрока, его звание и список званий для выбора. */
    public function show(User $user): JsonResponse
    {
        $user->loadMissing('bridgeRank');

        $submissions = UserBridgeTechnique::query()
            ->where('user_id', $user->id)
            ->with(['technique.variants', 'reviewer:id,username,avatar'])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'submissions' => BridgeSubmissionResource::collection($submissions)->resolve(),
            'rank' => $user->bridgeRank ? (new BridgeRankResource($user->bridgeRank))->resolve() : null,
            'ranks' => BridgeRankResource::collection($this->bridge->ranks())->resolve(),
            'summary' => $this->bridge->summary($user),
            'display_rank' => $user->fresh()->display_rank,
        ]);
    }

    /** Поправить оценки и статус заявки. */
    public function update(Request $request, UserBridgeTechnique $submission): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                UserBridgeTechnique::STATUS_DECLARED,
                UserBridgeTechnique::STATUS_CONFIRMED,
                UserBridgeTechnique::STATUS_REJECTED,
            ])],
            'stability' => ['nullable', 'integer', 'min:0', 'max:' . UserBridgeTechnique::MAX_ASPECT],
            'speed' => ['nullable', 'integer', 'min:0', 'max:' . UserBridgeTechnique::MAX_ASPECT],
            'difficulty' => ['nullable', 'integer', 'min:0', 'max:' . UserBridgeTechnique::MAX_ASPECT],
            'score' => ['nullable', 'integer', 'min:0', 'max:' . UserBridgeTechnique::MAX_SCORE],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Подтверждённый вид без оценок сломал бы топ и рейтинг
        if ($data['status'] === UserBridgeTechnique::STATUS_CONFIRMED) {
            foreach (['stability', 'speed', 'difficulty', 'score'] as $field) {
                abort_if(! isset($data[$field]), 422, 'Для подтверждённого вида нужны все оценки.');
            }
        }

        $before = $submission->only(['status', 'stability', 'speed', 'difficulty', 'score']);

        $submission->update([
            'status' => $data['status'],
            'stability' => $data['stability'] ?? null,
            'speed' => $data['speed'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'score' => $data['score'] ?? null,
            'review_notes' => $data['notes'] ?? $submission->review_notes,
            'reviewed_by' => $data['status'] === UserBridgeTechnique::STATUS_DECLARED ? null : $request->user()->id,
            'reviewed_at' => $data['status'] === UserBridgeTechnique::STATUS_DECLARED ? null : now(),
        ]);

        ModerationLog::record($request->user(), 'bridge.submission_updated', $submission->user_id, [
            'technique' => $submission->technique?->label,
            'before' => $before,
            'after' => $submission->only(['status', 'stability', 'speed', 'difficulty', 'score']),
        ]);

        app(RatingService::class)->recalculate([$submission->user_id]);

        return response()->json([
            'submission' => (new BridgeSubmissionResource(
                $submission->fresh()->load('technique.variants', 'reviewer:id,username,avatar')
            ))->resolve(),
        ]);
    }

    /** Удалить заявку целиком: игрок сможет подать вид заново. */
    public function destroy(Request $request, UserBridgeTechnique $submission): JsonResponse
    {
        $userId = $submission->user_id;
        $label = $submission->technique?->label;

        app(BridgeVideoService::class)->deleteFor($submission);
        $submission->delete();

        ModerationLog::record($request->user(), 'bridge.submission_deleted', $userId, ['technique' => $label]);

        app(RatingService::class)->recalculate([$userId]);

        return response()->json(['ok' => true]);
    }
}
