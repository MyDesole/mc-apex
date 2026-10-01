<?php

namespace App\Http\Requests\Tester;

use App\Http\Requests\BaseFormRequest;
use App\Models\TierTest;

/**
 * Оценки зависят от режима заявки: у pvp и bedwars разные поля.
 */
class CompleteTierTestRequest extends BaseFormRequest
{
    private const PVP_FIELDS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    private const BEDWARS_FIELDS = ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

    public function rules(): array
    {
        $fields = $this->mode() === 'pvp' ? self::PVP_FIELDS : self::BEDWARS_FIELDS;

        $rules = [];

        foreach ($fields as $field) {
            $rules[$field] = ['required', 'integer', 'min:0', 'max:20'];
        }

        $rules['notes'] = ['nullable', 'string', 'max:2000'];

        return $rules;
    }

    private function mode(): string
    {
        /** @var TierTest|null $tierTest */
        $tierTest = $this->route('tierTest');

        return $tierTest?->mode === 'pvp' ? 'pvp' : 'bedwars';
    }

    public function attributes(): array
    {
        return [
            'block_placing' => 'постановка блоков',
            'rotka' => 'ротка',
            'movement' => 'передвижение',
            'aim' => 'прицел',
            'game_sense' => 'игровое мышление',
            'bed_play' => 'игра у кроватей',
            'teamplay' => 'командная игра',
            'building' => 'строительство',
            'notes' => 'заметки',
        ];
    }
}
