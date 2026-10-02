<?php

namespace App\Domains\Clan\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class ApplyToClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
