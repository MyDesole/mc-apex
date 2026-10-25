<?php

namespace App\Domains\Core\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateSiteSettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'hero' => ['nullable', 'array'],
            'socials' => ['nullable', 'array'],
            'footer' => ['nullable', 'array'],
            'stats' => ['nullable', 'array'],
            // Очки общего рейтинга: только известные ключи и целые числа
            'rating' => ['nullable', 'array:rating_match_win,rating_war_participation,rating_war_win'],
            'rating.*' => ['integer', 'min:0', 'max:1000'],
        ];
    }
}
