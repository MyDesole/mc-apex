<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class StartDirectRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
