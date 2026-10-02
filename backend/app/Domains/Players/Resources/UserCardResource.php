<?php

namespace App\Domains\Players\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Краткая карточка игрока для списков, топов и заявок в друзья.
 *
 * Раньше этот массив собирался копипастой в нескольких местах
 * (друзья, рейтинг, участники клана) и успел разойтись по составу полей.
 *
 * @property \App\Domains\Users\Models\User $resource
 */
class UserCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
            'tier' => $user->tier,
            'tier_score' => (int) $user->tier_score,
            'is_verified' => (bool) $user->is_verified,
            'accent_color' => $user->accent_color,
            'banner_color' => $user->banner_color,
            'clan_tag' => $user->clan_tag,
            'clan_color' => $user->clan_color,
            'role' => $user->role,
            'is_media' => $user->isMedia(),
        ];
    }
}
