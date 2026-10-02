<?php

namespace App\Domains\Shop\Requests\Shop;

use App\Http\Requests\BaseFormRequest;

class PurchaseRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tier_test_id' => ['nullable', 'integer', 'exists:tier_tests,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function attributes(): array
    {
        return ['tier_test_id' => 'заявка', 'quantity' => 'количество'];
    }
}
