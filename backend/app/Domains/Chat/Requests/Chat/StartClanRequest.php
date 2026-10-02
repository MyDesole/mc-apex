<?php

namespace App\Domains\Chat\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class StartClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'clan_id' => ['required', 'integer', 'exists:clans,id'],
        ];
    }
}
