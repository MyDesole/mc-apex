<?php

namespace App\Domains\Clan\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Domains\Players\Resources\UserCardResource;

/**
 * Заявка на вступление в клан.
 *
 * @property \App\Domains\Clan\Models\ClanApplication $resource
 */
class ClanApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $application = $this->resource;

        return [
            'id' => $application->id,
            'status' => $application->status,
            'message' => $application->message,
            'created_at' => $application->created_at?->toIso8601String(),
            'user' => $application->relationLoaded('user') && $application->user
                ? new UserCardResource($application->user)
                : null,
        ];
    }
}
