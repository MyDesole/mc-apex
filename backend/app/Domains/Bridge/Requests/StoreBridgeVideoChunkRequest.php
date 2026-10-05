<?php

namespace App\Domains\Bridge\Requests;

use App\Http\Requests\BaseFormRequest;

/**
 * Одна часть загружаемого видео.
 */
class StoreBridgeVideoChunkRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:16384'], // 16 МБ с запасом
        ];
    }

    public function attributes(): array
    {
        return [
            'index' => 'номер части',
            'chunk' => 'часть файла',
        ];
    }
}
