<?php

namespace App\Domains\Achievements\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class GrantAchievementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'achievement_id' => ['required', 'integer', 'exists:achievements,id'],
        ];
    }
}
