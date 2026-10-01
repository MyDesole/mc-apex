<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заявка в друзья: сама связь плюс карточка второго участника.
 *
 * Контроллер сам вычислял, кто из пары «другой», и трижды повторял
 * одинаковый массив — теперь это одна ресурсная обёртка.
 *
 * @property \App\Models\Friendship $resource
 */
class FriendshipResource extends JsonResource
{
    /** id текущего пользователя, чтобы понять, кто в паре «другой». */
    private ?int $viewerId = null;

    public function forViewer(int $viewerId): self
    {
        $this->viewerId = $viewerId;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $friendship = $this->resource;
        $viewerId = $this->viewerId ?? $request->user()?->id;

        /** @var User|null $other */
        $other = $friendship->user_id === $viewerId ? $friendship->friend : $friendship->user;

        return [
            'id' => $friendship->id,
            'status' => $friendship->status,
            'initiated_by_me' => $friendship->user_id === $viewerId,
            'created_at' => $friendship->created_at?->toIso8601String(),
            'user' => $other ? new UserCardResource($other) : null,
        ];
    }
}
