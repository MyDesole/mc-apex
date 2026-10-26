<?php

namespace App\Domains\Tournaments\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateTournamentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'in:solo,clan'],
            'format' => ['sometimes', 'in:single_elim'],
            'status' => ['sometimes', 'in:draft,registration,ongoing,completed,cancelled'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'string', 'max:8'],
            'prize_description' => ['nullable', 'string', 'max:255'],
            'min_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_participants' => ['sometimes', 'integer', 'min:2', 'max:128'],
            'win_points' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Конец регистрации позже её начала — как при создании турнира.
     * Без этой проверки опечатка в годе молча закрывала регистрацию.
     * Сравниваем и с уже сохранённой датой: форма может прислать только одну.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $tournament = $this->route('tournament');

                $starts = $this->has('registration_starts_at')
                    ? $this->input('registration_starts_at')
                    : $tournament?->registration_starts_at;
                $ends = $this->has('registration_ends_at')
                    ? $this->input('registration_ends_at')
                    : $tournament?->registration_ends_at;

                if (! $starts || ! $ends || $validator->errors()->hasAny(['registration_starts_at', 'registration_ends_at'])) {
                    return;
                }

                if (Carbon::parse($ends)->lte(Carbon::parse($starts))) {
                    $validator->errors()->add('registration_ends_at', 'Конец регистрации должен быть позже её начала.');
                }
            },
        ];
    }
}
