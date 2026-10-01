<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заявка на вступление в клан.
 *
 * @property \App\Models\ClanApplication $resource
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
