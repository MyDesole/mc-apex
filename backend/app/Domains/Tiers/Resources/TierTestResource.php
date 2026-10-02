<?php

namespace App\Domains\Tiers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Domains\Players\Resources\UserCardResource;

/**
 * Заявка на тир-тест.
 *
 * Одна структура для очереди тестера, карточки заявки и истории игрока.
 * Состав полей зафиксирован тестом ResponseShapeTest — он не даст
 * случайно потерять поле, на которое опирается фронтенд.
 *
 * @property \App\Domains\Tiers\Models\TierTest $resource
 */
class TierTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $test = $this->resource;

        return [
            'id' => $test->id,
            'user_id' => $test->user_id,
            'tester_id' => $test->tester_id,
            'claimed_by' => $test->claimed_by,
            'mode' => $test->mode,
            'status' => $test->status,
            'is_priority' => (bool) $test->is_priority,
            'priority_weight' => (int) $test->priority_weight,
            'priority_purchased_at' => $test->priority_purchased_at?->toIso8601String(),
            'contact_type' => $test->contact_type,
            'contact_value' => $test->contact_value,
            'preferred_time' => $test->preferred_time,
            'notes' => $test->notes,
            'result_tier' => $test->result_tier,
            'result_score' => $test->result_score === null ? null : (int) $test->result_score,
            'aspects' => $test->aspects,
            'claimed_at' => $test->claimed_at?->toIso8601String(),
            'completed_at' => $test->completed_at?->toIso8601String(),
            'created_at' => $test->created_at?->toIso8601String(),
            'updated_at' => $test->updated_at?->toIso8601String(),

            'user' => $test->relationLoaded('user') && $test->user
                ? new UserCardResource($test->user)
                : null,

            'tester' => $test->relationLoaded('tester') && $test->tester
                ? ['id' => $test->tester->id, 'username' => $test->tester->username]
                : null,

            'claimer' => $test->relationLoaded('claimer') && $test->claimer
                ? ['id' => $test->claimer->id, 'username' => $test->claimer->username]
                : null,
        ];
    }
}
