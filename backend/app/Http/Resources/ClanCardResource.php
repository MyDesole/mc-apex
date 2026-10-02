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
            // Описание и лимит участников нужны карточке в списке кланов
            'description' => $clan->description,
            'max_members' => (int) $clan->max_members,
            'avatar' => $clan->avatar,
            'avatar_url' => $clan->avatar_url,
            'banner_color' => $clan->banner_color,
            'power' => (int) $clan->power,
            'wins' => (int) $clan->wins,
            'losses' => (int) $clan->losses,
            'is_open' => (bool) $clan->is_open,
            // Плата за вступление: заявитель видит её до подачи заявки
            'entry_fee' => (int) $clan->entry_fee,
            'is_highlighted' => $clan->isHighlightActive(),
            // id своего клана: по нему фронтенд ведёт клик во вкладку «Мой клан»
            'my_clan_id' => $clan->my_clan_id ?? null,
            // Срок подсветки: фронтенд показывает дату и гасит эффекты
            'highlight_until' => $clan->highlight_until?->toIso8601String(),
            'highlight_color' => $clan->highlight_color,
            'highlight_effect' => $clan->highlight_effect,
            'members_count' => (int) ($clan->members_count ?? 0),
            'leader' => $clan->relationLoaded('leader') && $clan->leader
                ? new UserCardResource($clan->leader)
                : null,
        ];
    }
}
