<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class CompleteClanWarRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'challenger_score' => ['required', 'integer', 'min:0', 'max:100'],
            'opponent_score' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
