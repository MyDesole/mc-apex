<?php

namespace App\Domains\News\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateNewsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'in:news,update,event,announcement'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
