<?php

namespace App\Domains\Auth\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class ResetPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'Код состоит из 6 цифр.',
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен быть не короче 8 символов.',
        ];
    }
}
