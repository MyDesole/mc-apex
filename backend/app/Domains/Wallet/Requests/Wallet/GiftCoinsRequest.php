<?php

namespace App\Domains\Wallet\Requests\Wallet;

use App\Http\Requests\BaseFormRequest;

class GiftCoinsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'сумма'];
    }
}
