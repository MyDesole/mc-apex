<?php

namespace App\Domains\Chat\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class UpdateMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
