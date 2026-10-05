<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Куратор добавляет вид бриджа.
 */
class StoreBridgeTechniqueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:96'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'название вида',
            'description' => 'описание',
        ];
    }
}
