<?php

namespace App\Domains\Tiers\Requests\Appeal;

use App\Domains\Tiers\Models\TierTestAppeal;
use App\Http\Requests\BaseFormRequest;

/**
 * Решение по апелляции. При одобрении нужны новые оценки режима теста.
 */
class ResolveAppealRequest extends BaseFormRequest
{
    private const PVP_FIELDS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    private const BEDWARS_FIELDS = ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

    public function rules(): array
    {
        $rules = [
            'decision' => ['required', 'in:'.TierTestAppeal::STATUS_APPROVED.','.TierTestAppeal::STATUS_REJECTED],
            'resolution' => ['required_if:decision,'.TierTestAppeal::STATUS_REJECTED, 'nullable', 'string', 'max:1000'],
        ];

        if ($this->input('decision') !== TierTestAppeal::STATUS_APPROVED) {
            return $rules;
        }

        $mode = $this->route('appeal')?->tierTest?->mode;
        $rules['aspects'] = ['required', 'array'];

        foreach ($mode === 'pvp' ? self::PVP_FIELDS : self::BEDWARS_FIELDS as $field) {
            $rules["aspects.{$field}"] = ['required', 'integer', 'min:0', 'max:20'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'resolution.required_if' => 'Объясните игроку, почему апелляция отклонена.',
        ];
    }
}
