<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:user,media,tester,moderator,admin'],
        ];
    }
}
