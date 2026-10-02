<?php

namespace App\Domains\Auth\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class ForgotPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
