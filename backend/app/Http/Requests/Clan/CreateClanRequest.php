<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:32', Rule::unique('clans', 'name')],
            'tag' => ['required', 'string', 'min:2', 'max:8', Rule::unique('clans', 'tag')],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_color' => ['nullable', 'string', 'max:16'],
            'is_open' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'название', 'tag' => 'тег'];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Клан с таким названием уже существует.',
            'tag.unique' => 'Такой тег уже занят.',
        ];
    }
}
