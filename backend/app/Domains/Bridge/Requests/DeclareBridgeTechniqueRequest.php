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

            // Видео сначала грузится частями, здесь — идентификатор загрузки
            'upload_id' => ['required', 'uuid', 'exists:bridge_video_uploads,uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'technique_id' => 'вид бриджа',
            'upload_id' => 'видео',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'Сначала загрузи видео.',
            'upload_id.exists' => 'Загрузка видео не найдена, попробуй ещё раз.',
        ];
    }
}
