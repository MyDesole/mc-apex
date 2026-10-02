<?php

namespace App\Domains\Auth\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class VerifyCodeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
