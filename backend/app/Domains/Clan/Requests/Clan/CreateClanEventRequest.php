<?php

namespace App\Domains\Clan\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class CreateClanEventRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:announcement,event,training'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
        ];
    }
}
