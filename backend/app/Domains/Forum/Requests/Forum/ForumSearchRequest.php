<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class ForumSearchRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }
}
