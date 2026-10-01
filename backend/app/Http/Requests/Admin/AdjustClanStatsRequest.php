<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class AdjustClanStatsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'wins' => ['sometimes', 'integer', 'min:-999', 'max:999'],
            'losses' => ['sometimes', 'integer', 'min:-999', 'max:999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
