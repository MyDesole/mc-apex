<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // Клан берём из маршрута: уникальность проверяем без учёта самой записи
        $clanId = $this->route('clan')?->id;

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:32', Rule::unique('clans', 'name')->ignore($clanId)],
            'tag' => ['sometimes', 'string', 'min:2', 'max:8', Rule::unique('clans', 'tag')->ignore($clanId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_color' => ['nullable', 'string', 'max:16'],
            'is_open' => ['boolean'],
            // is_highlighted намеренно не принимается: подсветка платная,
            // выдаётся покупкой в магазине, а не настройкой клана

            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'socials' => ['nullable', 'array'],
            'socials.discord' => ['nullable', 'string', 'max:255'],
            'socials.telegram' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.vk' => ['nullable', 'string', 'max:255'],
            'socials.website' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Клан с таким названием уже существует.',
            'tag.unique' => 'Такой тег уже занят.',
            'socials.website.url' => 'Ссылка на сайт указана неверно.',
        ];
    }
}
