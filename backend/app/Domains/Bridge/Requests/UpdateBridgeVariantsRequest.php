<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Игрок или тестер включает и выключает подвиды заявки.
 */
class UpdateBridgeVariantsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'variants' => ['nullable', 'array'],
            'variants.*' => ['integer', 'exists:bridge_technique_variants,id'],
        ];
    }

    public function attributes(): array
    {
        return ['variants' => 'подвиды'];
    }
}
