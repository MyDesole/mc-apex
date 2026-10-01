<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AchievementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $achievement = $this->route('achievement');
        $sometimes = $achievement ? 'sometimes' : 'required';

        return [
            'name' => [$sometimes, 'string', 'max:80'],
            'description' => [$sometimes, 'string', 'max:255'],
            'icon' => [$sometimes, 'string', 'max:32'],
            'color' => [$sometimes, 'string', 'max:16'],
            'rarity' => [$sometimes, 'in:common,rare,epic,legendary'],
            'coin_reward' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'points' => [$sometimes, 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            // code задаётся только при создании и должен быть уникальным
            'code' => $achievement
                ? ['sometimes', 'string', 'max:64', Rule::unique('achievements', 'code')->ignore($achievement->id)]
                : ['nullable', 'string', 'max:64', 'unique:achievements,code'],
        ];
    }
}
