<?php

namespace App\Domains\Wallet\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class GrantCoinsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'сумма', 'reason' => 'причина'];
    }
}
