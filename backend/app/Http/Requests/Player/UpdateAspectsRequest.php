<?php

namespace App\Http\Requests\Player;

use App\Http\Requests\BaseFormRequest;

/**
 * Аспекты зависят от режима: у pvp и bedwars разные наборы полей.
 * Поэтому правила собираются условно, а не проверяются в контроллере.
 */
class UpdateAspectsRequest extends BaseFormRequest
{
    private const PVP_FIELDS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    private const BEDWARS_FIELDS = ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

    public function rules(): array
    {
        $fields = $this->mode() === 'pvp' ? self::PVP_FIELDS : self::BEDWARS_FIELDS;

        $rules = ['mode' => ['required', 'in:pvp,bedwars']];

        foreach ($fields as $field) {
            $rules[$field] = ['required', 'integer', 'min:0', 'max:20'];
        }

        return $rules;
    }

    public function mode(): string
    {
        // Всё, что не 'pvp', считается bedwars — как было в исходном контроллере
        return $this->input('mode') === 'pvp' ? 'pvp' : 'bedwars';
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
        ];
    }
}
