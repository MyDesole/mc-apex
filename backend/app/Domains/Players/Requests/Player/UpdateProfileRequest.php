<?php

namespace App\Domains\Players\Requests\Player;

use App\Http\Requests\BaseFormRequest;

class UpdateProfileRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'avatar_frame' => ['nullable', 'string', 'max:32'],
            'profile_effect' => ['nullable', 'string', 'max:32'],
            'accent_color' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:64'],
            'quote' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:500'],
            'favorite_clan_id' => ['nullable', 'exists:clans,id'],
            'profile_visibility' => ['nullable', 'in:public,friends,private'],

            'discord_tag' => ['nullable', 'string', 'max:64'],

            'favorite_modes' => ['nullable', 'array'],
            'favorite_modes.*' => ['nullable', 'string', 'in:bedwars,skywars,duels,pvp,survival,other'],


            'card_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_visibility.in' => 'Видимость профиля: public, friends или private.',
            'favorite_modes.*.in' => 'Такого режима нет в списке.',
        ];
    }
}
