<?php

namespace App\Domains\Tiers\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Админская правка аспектов игрока.
 *
 * У этого эндпоинта исторически свой набор полей: независимо от режима
 * приходят имена pvp-набора, а для bedwars они раскладываются по колонкам
 * в контроллере. Правила это отражают — менять их нельзя, не сломав админку.
 */
class UpdateUserAspectsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:pvp,bedwars'],
            'block_placing' => ['required', 'integer', 'min:0', 'max:20'],
            'rotka' => ['required', 'integer', 'min:0', 'max:20'],
            'movement' => ['required', 'integer', 'min:0', 'max:20'],
            'aim' => ['required', 'integer', 'min:0', 'max:20'],
            'game_sense' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }
}
