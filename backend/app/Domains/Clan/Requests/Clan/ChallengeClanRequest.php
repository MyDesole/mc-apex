<?php

namespace App\Domains\Clan\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class ChallengeClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
