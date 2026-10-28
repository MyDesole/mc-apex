<?php

namespace App\Domains\Activity\Resources;

use App\Domains\Activity\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Событие ленты в том виде, в каком его рисует фронтенд.
 *
 * @property Activity $resource
 */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activity = $this->resource;
        $actor = $activity->actor;

        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'payload' => $activity->payload,
            'actor' => $actor ? [
                'id' => $actor->id,
                'username' => $actor->username,
                'avatar_url' => $actor->avatar_url,
                'tier' => $actor->publicTier(),
                'display_rank' => $actor->display_rank,
                'clan_tag' => $actor->clan_tag,
                'clan_color' => $actor->clan_color,
            ] : null,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
