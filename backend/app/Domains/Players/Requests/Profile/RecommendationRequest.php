<?php

namespace App\Domains\Players\Requests\Profile;

use App\Http\Requests\BaseFormRequest;

class RecommendationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:280'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
