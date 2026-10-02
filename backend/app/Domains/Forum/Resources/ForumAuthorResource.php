<?php

namespace App\Domains\Forum\Resources;

use App\Domains\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Автор сообщения в форуме: карточка плюс форумные признаки.
 *
 * @property User $resource
 */
class ForumAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->resource;

        return [
            'id' => $author->id,
            'username' => $author->username,
            'avatar_url' => $author->avatar_url,
            'tier' => $author->tier,
            'role' => $author->role,
            'is_media' => $author->isMedia(),
            'is_verified' => (bool) $author->is_verified,
            'equipped_badges' => $author->equipped_badges ?? [],
            'achievement_points' => $author->achievementPoints(),
            'clan_tag' => $author->relationLoaded('clanMember') ? $author->clanMember?->clan?->tag : null,
        ];
    }
}
