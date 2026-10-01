<?php

namespace App\Http\Requests\TierTest;

use App\Http\Requests\BaseFormRequest;

class UpdateTierTestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'result_tier' => ['nullable', 'in:S+,S,A,B,C,D,E'],
            'result_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aspects' => ['nullable', 'array'],
            'aspects.block_placing' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.rotka' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.movement' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.aim' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.game_sense' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.pvp' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.bed_play' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.teamplay' => ['nullable', 'integer', 'min:0', 'max:20'],
            'aspects.building' => ['nullable', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
