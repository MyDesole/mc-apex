<?php

namespace App\Http\Requests\Ranking;

use App\Http\Requests\BaseFormRequest;

class RankingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'in:overall,pvp,bedwars'],
            'cursor_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'cursor_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:64'],
            'tier' => ['nullable', 'string', 'max:4'],
            'clan_id' => ['nullable', 'integer', 'exists:clans,id'],
        ];
    }
}
