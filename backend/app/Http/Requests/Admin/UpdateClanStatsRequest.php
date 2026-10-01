<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateClanStatsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'power' => ['sometimes', 'integer', 'min:0'],
            'wins' => ['sometimes', 'integer', 'min:0'],
            'losses' => ['sometimes', 'integer', 'min:0'],
            'is_highlighted' => ['sometimes', 'boolean'],
        ];
    }
}
