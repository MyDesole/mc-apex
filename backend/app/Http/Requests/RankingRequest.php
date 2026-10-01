<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RankingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => [
                'nullable',
                'string',
                Rule::in(['overall', 'pvp', 'bedwars']),
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'search' => [
                'nullable',
                'string',
                'max:50',
            ],

            'tier' => [
                'nullable',
                'string',
                'max:10',
            ],

            'clan_id' => [
                'nullable',
                'integer',
                'exists:clans,id',
            ],

            'cursor' => [
                'nullable',
                'array',
            ],

            'cursor.score' => [
                'nullable',
                'integer',
            ],

            'cursor.id' => [
                'nullable',
                'integer',
            ],

            'cursor.offset' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}
