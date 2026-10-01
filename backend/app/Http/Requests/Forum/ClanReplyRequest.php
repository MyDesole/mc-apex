<?php

namespace App\Http\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class ClanReplyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:clan_forum_replies,id'],
        ];
    }
}
