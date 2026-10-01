<?php

namespace App\Http\Requests\Tester;

use App\Http\Requests\BaseFormRequest;

class CancelTierTestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
