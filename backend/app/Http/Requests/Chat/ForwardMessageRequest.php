<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class ForwardMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ];
    }
}
