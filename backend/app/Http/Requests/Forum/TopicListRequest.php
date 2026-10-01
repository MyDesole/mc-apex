<?php

namespace App\Http\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class TopicListRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:activity,new,popular,unanswered'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
