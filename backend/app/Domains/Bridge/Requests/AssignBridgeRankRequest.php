<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Тестер присваивает звание бриджера.
 *
 * rank_id = null снимает звание.
 */
class AssignBridgeRankRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'rank_id' => ['nullable', 'integer', 'exists:bridge_ranks,id'],
        ];
    }

    public function attributes(): array
    {
        return ['rank_id' => 'звание'];
    }
}
