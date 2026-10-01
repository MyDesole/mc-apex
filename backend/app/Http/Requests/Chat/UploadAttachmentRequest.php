<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class UploadAttachmentRequest extends BaseFormRequest
{
    /** До 12 МБ. */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:12288',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm,pdf,txt,md,zip,rar,7z,json',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Такой файл приложить нельзя: разрешены картинки, видео, pdf, тексты и архивы.',
            'file.max' => 'Файл больше 12 МБ.',
        ];
    }
}
