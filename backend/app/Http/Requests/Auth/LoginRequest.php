<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Логином может быть и почта, и ник — формат проверяет сервис
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => 'логин', 'password' => 'пароль'];
    }
}
