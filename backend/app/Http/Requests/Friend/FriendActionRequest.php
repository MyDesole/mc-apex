<?php

namespace App\Http\Requests\Friend;

use App\Http\Requests\BaseFormRequest;

class FriendActionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
