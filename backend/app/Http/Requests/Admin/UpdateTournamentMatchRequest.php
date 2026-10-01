<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateTournamentMatchRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'score1' => ['nullable', 'integer', 'min:0'],
            'score2' => ['nullable', 'integer', 'min:0'],
            'winner_id' => ['nullable', 'exists:tournament_participants,id'],
            'status' => ['sometimes', 'in:pending,ready,live,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
