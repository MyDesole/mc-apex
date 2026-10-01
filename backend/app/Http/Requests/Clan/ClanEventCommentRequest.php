<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class ClanEventCommentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            'parent_id' => ['nullable', 'exists:clan_event_comments,id'],
        ];
    }
}
