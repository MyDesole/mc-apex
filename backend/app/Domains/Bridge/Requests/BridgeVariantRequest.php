<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Куратор добавляет или правит подвид бриджа.
 */
class BridgeVariantRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // При создании название обязательно, при правке — по желанию
            'label' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:96'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['label' => 'название подвида'];
    }
}
