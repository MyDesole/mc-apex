<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Работа с файлами пользователя на диске public.
 *
 * Отдельный трейт, потому что удаление старого файла перед записью нового
 * повторяется в нескольких сервисах, и легко забыть его вычистить.
 */
trait StoresUserFiles
{
    /**
     * Удалить ранее сохранённый файл, если он есть.
     * Путь из БД, поэтому проверяем существование, а не падаем.
     */
    protected function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
