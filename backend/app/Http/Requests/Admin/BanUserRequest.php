<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class BanUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
