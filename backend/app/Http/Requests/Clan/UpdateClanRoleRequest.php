<?php

namespace App\Http\Requests\Clan;

use App\Http\Requests\BaseFormRequest;

class UpdateClanRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:officer,member'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:news,forum,applications,wars,resources'],
            'title' => ['nullable', 'string', 'max:32'],
        ];
    }
}
