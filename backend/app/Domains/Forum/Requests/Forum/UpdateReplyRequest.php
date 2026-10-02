<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class UpdateReplyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:20000'],
        ];
    }
}
