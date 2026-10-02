<?php

namespace App\Domains\Auth\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class SendVerificationCodeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Этот email уже зарегистрирован.',
        ];
    }
}
