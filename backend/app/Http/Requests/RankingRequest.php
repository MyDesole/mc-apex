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

            // Курсор можно передать двумя способами:
            //   cursor[score]=..&cursor[id]=..&cursor[offset]=..
            //   cursor=score,id,offset
            'cursor' => [
                'nullable',
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

            // Плоские ключи: понимает и фронтенд, и старые клиенты
            'cursor_score' => [
                'nullable',
                'integer',
            ],

            'cursor_id' => [
                'nullable',
                'integer',
            ],

            'offset' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Курсор в едином виде независимо от формы запроса.
     *
     * @return array{score: int, id: int, offset: int}|null
     */
    public function cursor(): ?array
    {
        $cursor = $this->input('cursor');

        // Строка вида "score,id,offset"
        if (is_string($cursor) && $cursor !== '') {
            $parts = array_map('intval', explode(',', $cursor));

            return [
                'score' => $parts[0] ?? 0,
                'id' => $parts[1] ?? 0,
                'offset' => $parts[2] ?? 0,
            ];
        }

        // Объект cursor[...]
        if (is_array($cursor)) {
            return [
                'score' => (int) ($cursor['score'] ?? 0),
                'id' => (int) ($cursor['id'] ?? 0),
                'offset' => (int) ($cursor['offset'] ?? 0),
            ];
        }

        // Плоские ключи cursor_score/cursor_id/offset
        if ($this->filled('cursor_score') || $this->filled('cursor_id')) {
            return [
                'score' => (int) $this->input('cursor_score', 0),
                'id' => (int) $this->input('cursor_id', 0),
                'offset' => (int) $this->input('offset', 0),
            ];
        }

        return null;
    }
}
