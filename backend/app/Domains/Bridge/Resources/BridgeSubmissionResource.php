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

            /*
             * Видео лежит в закрытом хранилище, поэтому отдаём подписанную
             * ссылку на время просмотра. Внешняя ссылка остаётся только у
             * старых заявок.
             */
            'video_url' => $row->video_path
                ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'bridge.video',
                    now()->addHours((int) config('bridge.video_url_ttl_hours', 3)),
                    ['submission' => $row->id],
                )
                : $row->video_url,

            'has_video' => (bool) ($row->video_path || $row->video_url),

            // Какие подвиды заявлены: id + подписи для показа
            'variants' => $this->variantPayload($row),

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

    /**
     * Подвиды заявки: id и подписи.
     *
     * Отдаём и id, и подписи: странице нужны подписи для показа, а
     * переключателям — id, чтобы не сверять их со справочником.
     *
     * @return array<int, array{id:int, label:string, is_special:bool}>
     */
    private function variantPayload(\App\Domains\Bridge\Models\UserBridgeTechnique $row): array
    {
        $ids = $row->variants ?? [];

        if ($ids === [] || ! $row->relationLoaded('technique') || ! $row->technique) {
            return [];
        }

        $all = $row->technique->relationLoaded('variants')
            ? $row->technique->variants
            : $row->technique->variants()->get();

        return $all
            ->whereIn('id', $ids)
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'label' => $variant->label,
                'is_special' => (bool) $variant->is_special,
            ])
            ->values()
            ->all();
    }
}
