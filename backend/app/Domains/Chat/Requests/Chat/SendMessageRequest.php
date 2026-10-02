<?php

namespace App\Domains\Chat\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class SendMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'forwarded_from_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['integer', 'exists:message_attachments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'текст сообщения',
            'reply_to_id' => 'сообщение для ответа',
        ];
    }
}
