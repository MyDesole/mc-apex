<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Куратор правит вид бриджа.
 *
 * Все поля необязательные: правят обычно одно поле за раз.
 */
class UpdateBridgeTechniqueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:96'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
