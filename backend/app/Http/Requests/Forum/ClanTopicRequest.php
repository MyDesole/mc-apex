<?php

namespace App\Http\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class ClanTopicRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
