<?php

namespace App\Domains\Auth\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class RegisterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'min:3', 'max:32',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'verification_token' => ['required', 'string'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Ник может содержать только латинские буквы, цифры и подчёркивание.',
            'username.unique' => 'Такой ник уже занят.',
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен быть не короче 8 символов.',
        ];
    }
}
