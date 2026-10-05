<?php

namespace App\Domains\Bridge\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сессия чанковой загрузки видео.
 *
 * Пока идёт загрузка, части лежат во временной папке. После сборки здесь
 * остаётся путь к готовому файлу.
 */
class BridgeVideoUpload extends Model
{
    protected $table = 'bridge_video_uploads';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    /** Наибольший размер ролика. */
    public const MAX_SIZE = 314572800; // 300 МБ

    /** Размер одной части. */
    public const CHUNK_SIZE = 4194304; // 4 МБ

    protected $fillable = [
        'uuid', 'user_id', 'original_name', 'mime', 'size',
        'total_chunks', 'received_chunks', 'path', 'status',
    ];

    protected $casts = [
        'received_chunks' => 'array',
        'size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Все ли части на месте. */
    public function isComplete(): bool
    {
        return count($this->received_chunks ?? []) >= $this->total_chunks;
    }
}
