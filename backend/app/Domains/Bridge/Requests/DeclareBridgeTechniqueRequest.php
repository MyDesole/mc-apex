<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Игрок отмечает вид бриджа и прикладывает видео.
 *
 * Видео обязательно: подтверждать нечего, если ролика нет. Ссылка ведёт
 * на внешний хостинг (YouTube, Streamable и подобные).
 */
class DeclareBridgeTechniqueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'technique_id' => ['required', 'integer', 'exists:bridge_techniques,id'],
            'video_url' => ['required', 'string', 'max:512', 'url'],
        ];
    }

    public function attributes(): array
    {
        return [
            'technique_id' => 'вид бриджа',
            'video_url' => 'ссылка на видео',
        ];
    }

    public function messages(): array
    {
        return [
            'video_url.url' => 'Ссылка на видео должна быть полным адресом (https://...).',
        ];
    }
}
