<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateTournamentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'in:solo,clan'],
            'format' => ['sometimes', 'in:single_elim,double_elim,round_robin'],
            'status' => ['sometimes', 'in:draft,registration,ongoing,completed,cancelled'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'string', 'max:8'],
            'prize_description' => ['nullable', 'string', 'max:255'],
            'min_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_participants' => ['sometimes', 'integer', 'min:2', 'max:128'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
