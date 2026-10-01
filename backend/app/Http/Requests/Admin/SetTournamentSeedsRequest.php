<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class SetTournamentSeedsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'seeds' => ['required', 'array'],
            'seeds.*.id' => ['required', 'exists:tournament_participants,id'],
            'seeds.*.seed' => ['required', 'integer', 'min:1'],
        ];
    }
}
