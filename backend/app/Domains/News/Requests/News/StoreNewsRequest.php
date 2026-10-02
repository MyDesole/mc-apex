<?php

namespace App\Domains\News\Requests\News;

use App\Http\Requests\BaseFormRequest;

class StoreNewsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'min:1'],
            'is_published' => ['boolean'],
            'is_pinned' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
