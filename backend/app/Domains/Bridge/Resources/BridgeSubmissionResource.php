<?php

namespace App\Domains\Bridge\Resources;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заявка игрока на вид бриджа.
 *
 * Общая статистика отдаётся готовым числом (0–300): сумма трёх аспектов,
 * чтобы страница не считала её сама в нескольких местах.
 *
 * @property UserBridgeTechnique $resource
 */
class BridgeSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->id,
            'status' => $row->status,
            'is_confirmed' => $row->isConfirmed(),
            'video_url' => $row->video_url,

            'stability' => $row->stability,
            'speed' => $row->speed,
            'difficulty' => $row->difficulty,
            'total' => $row->total,
            'max_total' => UserBridgeTechnique::MAX_ASPECT * 3,
            'score' => $row->score,
            'max_score' => UserBridgeTechnique::MAX_SCORE,

            'review_notes' => $row->review_notes,
            'reviewed_at' => $row->reviewed_at?->toIso8601String(),
            'reviewer' => $row->relationLoaded('reviewer') && $row->reviewer
                ? ['id' => $row->reviewer->id, 'username' => $row->reviewer->username]
                : null,

            'technique' => $row->relationLoaded('technique') && $row->technique
                ? (new BridgeTechniqueResource($row->technique))->resolve()
                : null,

            'user' => $row->relationLoaded('user') && $row->user
                ? (new \App\Domains\Players\Resources\UserCardResource($row->user))->resolve()
                : null,

            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
