<?php

namespace App\Http\Requests\TierTest;

use App\Http\Requests\BaseFormRequest;

class CreateTierTestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:pvp,bedwars'],
            'contact_type' => ['required', 'in:discord,telegram'],
            'contact_value' => ['required', 'string', 'max:128'],
            'preferred_time' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_value' => 'контакт',
            'preferred_time' => 'удобное время',
        ];
    }
}
