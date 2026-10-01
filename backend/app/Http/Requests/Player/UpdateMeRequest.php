<?php

namespace App\Http\Requests\Player;

use App\Http\Requests\BaseFormRequest;

class UpdateMeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:500'],
            'banner_color' => ['nullable', 'string', 'max:16'],

            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'socials' => ['nullable', 'array'],
            'socials.discord' => ['nullable', 'string', 'max:255'],
            'socials.telegram' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.vk' => ['nullable', 'string', 'max:255'],
            // Без правила 'url': ссылки часто пишут без схемы
            'socials.website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'avatar' => 'аватар',
            'cover' => 'обложка',
            'bio' => 'описание',
        ];
    }
}
