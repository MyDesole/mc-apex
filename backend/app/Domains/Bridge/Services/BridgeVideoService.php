<?php

namespace App\Domains\Bridge\Services;

use App\Domains\Bridge\Models\BridgeVideoUpload;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Видео бридж-заявок: чанковая загрузка, отдача и удаление.
 *
 * Ролик грузится частями и лежит в закрытом хранилище. После того как
 * тестер провёл проверку, видео удаляется — хранить его дальше незачем.
 */
class BridgeVideoService
{
    use AbortsWithMessage;

    /** Куда складываем готовые ролики. */
    private const DIR = 'bridge-videos';

    /** Куда складываем части во время загрузки. */
    private const CHUNK_DIR = 'bridge-chunks';

    /** Разрешённые типы видео. */
    private const ALLOWED_MIME = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-matroska',
        'video/x-msvideo',
    ];

    private function disk()
    {
        return Storage::disk('local');
    }

    /* ----------------------------- Загрузка ----------------------------- */

    /**
     * Начинает загрузку: создаёт сессию и временную папку.
     *
     * @return array{uuid:string, chunk_size:int, received:array<int>}
     */
    public function init(User $user, string $fileName, int $size, string $mime): array
    {
        if ($size <= 0) {
            $this->abortUnprocessable('Пустой файл.');
        }

        if ($size > BridgeVideoUpload::MAX_SIZE) {
            $this->abortUnprocessable('Видео больше 300 МБ — сожми ролик.');
        }

        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            $this->abortUnprocessable('Такой формат видео не поддерживается. Нужен MP4, WebM или MOV.');
        }

        $totalChunks = (int) ceil($size / BridgeVideoUpload::CHUNK_SIZE);

        $upload = BridgeVideoUpload::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'original_name' => Str::limit($fileName, 255, ''),
            'mime' => $mime,
            'size' => $size,
            'total_chunks' => $totalChunks,
            'received_chunks' => [],
            'status' => BridgeVideoUpload::STATUS_PENDING,
        ]);

        $this->disk()->makeDirectory(self::CHUNK_DIR . '/' . $upload->uuid);

        return [
            'uuid' => $upload->uuid,
            'chunk_size' => BridgeVideoUpload::CHUNK_SIZE,
            'received' => [],
        ];
    }

    /**
     * Принимает одну часть.
     *
     * Повторная часть перезаписывается: после обрыва связи фронтенд
     * повторяет неудачную, и это не должно ломать загрузку.
     *
     * @return array{received:array<int>, total:int, complete:bool}
     */
    public function storeChunk(User $user, BridgeVideoUpload $upload, int $index, UploadedFile $chunk): array
    {
        $this->assertOwner($user, $upload);

        if ($upload->isCompleted()) {
            $this->abortUnprocessable('Загрузка уже завершена.');
        }

        if ($index < 0 || $index >= $upload->total_chunks) {
            $this->abortUnprocessable('Такой части нет в этой загрузке.');
        }

        $this->disk()->putFileAs(
            self::CHUNK_DIR . '/' . $upload->uuid,
            $chunk,
            (string) $index,
        );

        $received = $upload->received_chunks ?? [];

        if (! in_array($index, $received, true)) {
            $received[] = $index;
            sort($received);
        }

        $upload->update(['received_chunks' => $received]);

        return [
            'received' => $received,
            'total' => $upload->total_chunks,
            'complete' => count($received) >= $upload->total_chunks,
        ];
    }

    /**
     * Собирает части в один файл.
     *
     * Пишем потоком: ролик на сотни мегабайт нельзя держать в памяти.
     */
    public function complete(User $user, BridgeVideoUpload $upload): BridgeVideoUpload
    {
        $this->assertOwner($user, $upload);

        if ($upload->isCompleted()) {
            return $upload;
        }

        if (! $upload->isComplete()) {
            $this->abortUnprocessable(
                'Загружены не все части: ' . count($upload->received_chunks ?? [])
                . ' из ' . $upload->total_chunks . '.'
            );
        }

        $extension = $this->extensionFor($upload);
        $path = self::DIR . '/' . $upload->uuid . '.' . $extension;

        $target = $this->disk()->path($path);
        $this->disk()->makeDirectory(self::DIR);

        $out = fopen($target, 'wb');

        if ($out === false) {
            $this->abortUnprocessable('Не удалось создать файл на сервере.');
        }

        try {
            for ($index = 0; $index < $upload->total_chunks; $index++) {
                $chunkPath = $this->disk()->path(self::CHUNK_DIR . '/' . $upload->uuid . '/' . $index);

                if (! is_file($chunkPath)) {
                    $this->abortUnprocessable("Часть {$index} потерялась, загрузи ролик заново.");
                }

                $in = fopen($chunkPath, 'rb');

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        /*
         * Сверяем размер с заявленным. Без этой проверки неверные по
         * размеру части давали молча обрезанный ролик: в базе один размер,
         * на диске другой, и тестер смотрел бы не то, что прислали.
         */
        $actual = $this->disk()->size($path);

        if ($actual !== $upload->size) {
            $this->disk()->delete($path);

            $this->abortUnprocessable(
                "Собранный файл весит {$actual} байт вместо {$upload->size}. "
                . 'Загрузи ролик заново.'
            );
        }

        // Части больше не нужны
        $this->disk()->deleteDirectory(self::CHUNK_DIR . '/' . $upload->uuid);

        $upload->update([
            'path' => $path,
            'status' => BridgeVideoUpload::STATUS_COMPLETED,
            'received_chunks' => [],
        ]);

        return $upload->fresh();
    }

    /* ----------------------------- Отдача ----------------------------- */

    /** Полный путь к файлу заявки, если он есть. */
    public function pathFor(UserBridgeTechnique $submission): ?string
    {
        if (! $submission->video_path) {
            return null;
        }

        return $this->disk()->exists($submission->video_path)
            ? $this->disk()->path($submission->video_path)
            : null;
    }

    /** Тип содержимого по расширению. */
    public function mimeFor(UserBridgeTechnique $submission): string
    {
        return match (pathinfo((string) $submission->video_path, PATHINFO_EXTENSION)) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }

    /* ----------------------------- Удаление ----------------------------- */

    /**
     * Удаляет видео заявки.
     *
     * Вызывается, когда тест проведён: держать ролики дальше незачем,
     * а места они занимают много.
     */
    public function deleteFor(UserBridgeTechnique $submission): void
    {
        if ($submission->video_path) {
            $this->disk()->delete($submission->video_path);
        }

        if ($submission->video_path !== null) {
            $submission->forceFill(['video_path' => null])->save();
        }
    }

    /** Подчищает незавершённые загрузки старше суток и брошенные части. */
    public function pruneStale(): int
    {
        $stale = BridgeVideoUpload::query()
            ->where('status', BridgeVideoUpload::STATUS_PENDING)
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($stale as $upload) {
            $this->disk()->deleteDirectory(self::CHUNK_DIR . '/' . $upload->uuid);
            $upload->delete();
        }

        return $stale->count();
    }

    /* ----------------------------- Вспомогательное ----------------------------- */

    private function assertOwner(User $user, BridgeVideoUpload $upload): void
    {
        if ($upload->user_id !== $user->id) {
            $this->abortForbidden('Это не ваша загрузка.');
        }
    }

    private function extensionFor(BridgeVideoUpload $upload): string
    {
        return match ($upload->mime) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-matroska' => 'mkv',
            'video/x-msvideo' => 'avi',
            default => 'mp4',
        };
    }
}
