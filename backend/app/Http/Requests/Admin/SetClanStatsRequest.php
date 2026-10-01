<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class SetClanStatsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'wins' => ['required', 'integer', 'min:0', 'max:100000'],
            'losses' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
