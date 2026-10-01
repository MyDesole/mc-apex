<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class ConversationHistoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'mark_read' => ['nullable', 'boolean'],
        ];
    }
}
