<?php

namespace App\Domains\Tiers\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Проведение тир-теста админом: поля аспектов зависят от режима.
 */
class ConductTierTestRequest extends BaseFormRequest
{
    private const PVP_FIELDS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    private const BEDWARS_FIELDS = ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

    public function rules(): array
    {
        $fields = $this->input('mode') === 'pvp' ? self::PVP_FIELDS : self::BEDWARS_FIELDS;

        $rules = [
            'mode' => ['required', 'in:pvp,bedwars'],
            'aspects' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ($fields as $field) {
            $rules["aspects.{$field}"] = ['required', 'integer', 'min:0', 'max:20'];
        }

        return $rules;
    }
}
