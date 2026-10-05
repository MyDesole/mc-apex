<?php

namespace App\Domains\Users\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:user,media,tester,bridge_tester,bridge_curator,moderator,admin'],
        ];
    }
}
