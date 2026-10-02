<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class UpdateTopicRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:4', 'max:200'],
            'body' => ['sometimes', 'string', 'min:4', 'max:30000'],
        ];
    }
}
