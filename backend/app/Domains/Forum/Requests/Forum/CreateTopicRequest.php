<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class CreateTopicRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:forum_categories,id'],
            'title' => ['required', 'string', 'min:4', 'max:200'],
            'body' => ['required', 'string', 'min:4', 'max:30000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['integer', 'exists:forum_attachments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'раздел',
            'title' => 'заголовок',
            'body' => 'текст',
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Заголовок должен быть не короче 4 символов.',
            'body.min' => 'Текст темы должен быть не короче 4 символов.',
        ];
    }
}
