<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Клан для карточек, списков и топа.
 *
 * @property \App\Models\Clan $resource
 */
class ClanCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clan = $this->resource;

        return [
            'id' => $clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'avatar' => $clan->avatar,
            'avatar_url' => $clan->avatar_url,
            'banner_color' => $clan->banner_color,
            'power' => (int) $clan->power,
            'wins' => (int) $clan->wins,
            'losses' => (int) $clan->losses,
            'is_open' => (bool) $clan->is_open,
            'is_highlighted' => (bool) $clan->is_highlighted,
            'members_count' => (int) ($clan->members_count ?? 0),
            'leader' => $clan->relationLoaded('leader') && $clan->leader
                ? new UserCardResource($clan->leader)
                : null,
        ];
    }
}
