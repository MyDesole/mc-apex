<?php

namespace App\Domains\Bridge\Requests;

use App\Domains\Bridge\Models\BridgeVideoUpload;
use App\Http\Requests\BaseFormRequest;

/**
 * Начало чанковой загрузки видео.
 */
class InitBridgeVideoRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . BridgeVideoUpload::MAX_SIZE],
            'mime' => ['required', 'string', 'max:96'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file_name' => 'имя файла',
            'size' => 'размер',
            'mime' => 'формат',
        ];
    }

    public function messages(): array
    {
        return [
            'size.max' => 'Видео больше 300 МБ — сожми ролик.',
        ];
    }
}
