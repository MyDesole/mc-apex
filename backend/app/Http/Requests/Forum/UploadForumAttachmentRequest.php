<?php

namespace App\Http\Requests\Forum;

use App\Http\Requests\BaseFormRequest;

class UploadForumAttachmentRequest extends BaseFormRequest
{
    /** До 10 МБ. */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,md,zip,rar,7z,json,log,cfg,yml,yaml',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Такой файл приложить нельзя: разрешены картинки, pdf, тексты и архивы.',
            'file.max' => 'Файл больше 10 МБ.',
        ];
    }
}
