<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class VerifyUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:128'],
        ];
    }
}
