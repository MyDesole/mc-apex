<?php

namespace App\Domains\Chat\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

/**
 * Загрузка вложений к сообщению.
 *
 * Поддерживаются оба вида полезной нагрузки:
 *   - file  — один файл (обратная совместимость со старым клиентом);
 *   - files — массив файлов, чтобы пачка грузилась одним запросом.
 *
 * Если пришёл массив, он имеет приоритет: смешанный запрос обрабатывается
 * как пакетный.
 */
class UploadAttachmentRequest extends BaseFormRequest
{
    /** Сколько файлов можно приложить за один запрос. */
    public const MAX_BATCH = 10;

    private const MAX_KILOBYTES = 12288; // 12 МБ

    private const ALLOWED_MIMES = 'jpg,jpeg,png,webp,gif,mp4,webm,pdf,txt,md,zip,rar,7z,json';

    public function rules(): array
    {
        $isBatch = $this->hasBatch();

        return [
            'file' => [$isBatch ? 'nullable' : 'required_without:files', 'file', "max:" . self::MAX_KILOBYTES, 'mimes:' . self::ALLOWED_MIMES],

            'files' => ['nullable', 'array', 'min:1', 'max:' . self::MAX_BATCH],
            'files.*' => ['file', "max:" . self::MAX_KILOBYTES, 'mimes:' . self::ALLOWED_MIMES],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Такой файл приложить нельзя: разрешены картинки, видео, pdf, тексты и архивы.',
            'file.max' => 'Файл больше 12 МБ.',
            'files.max' => 'За один раз можно приложить не больше :max файлов.',
            'files.*.mimes' => 'Один из файлов приложить нельзя: разрешены картинки, видео, pdf, тексты и архивы.',
            'files.*.max' => 'Один из файлов больше 12 МБ.',
        ];
    }

    /**
     * Пришёл ли пакетный запрос.
     */
    public function hasBatch(): bool
    {
        return $this->has('files') && is_array($this->input('files'));
    }

    /**
     * Файлы для загрузки — единый список независимо от формы запроса.
     *
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    public function filesToStore(): array
    {
        if ($this->hasBatch()) {
            return array_values(array_filter($this->file('files') ?? []));
        }

        $single = $this->file('file');

        return $single ? [$single] : [];
    }
}
