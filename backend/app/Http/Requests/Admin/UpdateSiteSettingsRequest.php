<?php

namespace App\Http\Requests\Admin;

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
        ];
    }
}
