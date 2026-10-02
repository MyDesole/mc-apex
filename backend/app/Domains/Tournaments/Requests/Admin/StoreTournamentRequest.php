<?php

namespace App\Domains\Tournaments\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class StoreTournamentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:solo,clan'],
            'format' => ['required', 'in:single_elim,double_elim,round_robin'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'string', 'max:8'],
            'prize_description' => ['nullable', 'string', 'max:255'],
            'min_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_participants' => ['required', 'integer', 'min:2', 'max:128'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after:registration_starts_at'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
