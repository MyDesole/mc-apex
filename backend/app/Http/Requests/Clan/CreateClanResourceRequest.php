<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class CreateClanResourceRequest extends BaseFormRequest
{
    /** До 50 МБ: паки и конфиги бывают крупными. */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'in:resource_pack,screenshot,config,guide,other'],
            'file' => ['required', 'file', 'max:51200'],
            'is_public' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название',
            'category' => 'категория',
            'file' => 'файл',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Категория: resource_pack, screenshot, config, guide или other.',
            'file.max' => 'Файл больше 50 МБ.',
        ];
    }
}
