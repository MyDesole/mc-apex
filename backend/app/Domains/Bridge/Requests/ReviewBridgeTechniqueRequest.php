<?php

namespace App\Domains\Bridge\Requests;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Тестер проверяет вид бриджа: подтверждает или отклоняет.
 *
 * При подтверждении обязательны все оценки: без них вид не попадает в топ
 * и игрок не понимает, за что получил балл.
 */
class ReviewBridgeTechniqueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $max = UserBridgeTechnique::MAX_ASPECT;
        $maxScore = UserBridgeTechnique::MAX_SCORE;

        return [
            'confirm' => ['required', 'boolean'],

            'stability' => ['required_if:confirm,true', 'nullable', 'integer', "min:0", "max:{$max}"],
            'speed' => ['required_if:confirm,true', 'nullable', 'integer', 'min:0', "max:{$max}"],
            'difficulty' => ['required_if:confirm,true', 'nullable', 'integer', 'min:0', "max:{$max}"],
            'score' => ['required_if:confirm,true', 'nullable', 'integer', 'min:0', "max:{$maxScore}"],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'stability' => 'стабильность',
            'speed' => 'скорость',
            'difficulty' => 'сложность',
            'score' => 'владение видом',
        ];
    }
}
