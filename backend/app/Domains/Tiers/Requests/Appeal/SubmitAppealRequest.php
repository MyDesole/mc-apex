<?php

namespace App\Domains\Tiers\Requests\Appeal;

use App\Http\Requests\BaseFormRequest;

class SubmitAppealRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Опишите подробнее, с чем вы не согласны (минимум 20 символов).',
        ];
    }
}
