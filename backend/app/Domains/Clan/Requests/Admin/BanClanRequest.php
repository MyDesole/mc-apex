<?php

namespace App\Domains\Clan\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class BanClanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
