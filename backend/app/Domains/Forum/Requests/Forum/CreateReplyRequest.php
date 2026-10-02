<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class CreateReplyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:20000'],
            'parent_id' => ['nullable', 'integer', 'exists:forum_replies,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['integer', 'exists:forum_attachments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'текст ответа',
            'parent_id' => 'сообщение для ответа',
        ];
    }
}
