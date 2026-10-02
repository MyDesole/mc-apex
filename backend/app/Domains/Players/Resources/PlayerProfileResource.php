<?php

namespace App\Domains\Players\Resources;

use App\Domains\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Полный профиль игрока: все поля пользователя плюс аспекты и достижения.
 *
 * Заменил PlayerPresenter::profile(). Поля пользователя отдаём как есть —
 * их состав определяет модель, а фронтенд читает аспекты и достижения
 * отдельными ключами.
 *
 * @property User $resource
 */
class PlayerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return array_merge($user->toArray(), [
            // Единой связи aspects у User нет: это два разных отношения
            'aspects' => [
                'pvp' => $user->aspectPvp,
                'bedwars' => $user->aspectBedwars,
            ],
            'all_achievements' => $user->relationLoaded('achievements')
                ? $user->achievements->map(fn ($achievement) => [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'icon' => $achievement->icon,
                    'color' => $achievement->color,
                    'description' => $achievement->description,
                    'points' => $achievement->points,
                    'earned_at' => $achievement->pivot->earned_at ?? null,
                ])->values()->all()
                : [],
        ]);
    }
}
