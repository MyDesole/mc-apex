<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumAttachment extends Model
{
    protected $fillable = [
        'user_id', 'attachable_type', 'attachable_id',
        'original_name', 'path', 'mime', 'size', 'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'size' => 'integer',
    ];

    protected $appends = ['url', 'human_size'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        foreach (['Б', 'КБ', 'МБ', 'ГБ'] as $index => $unit) {
            if ($bytes < 1024 || $index === 3) {
                return round($bytes, $index === 0 ? 0 : 1) . ' ' . $unit;
            }

            $bytes /= 1024;
        }

        return $bytes . ' Б';
    }

    /**
     * Прикрепить файлы к объекту (теме или ответу).
     *
     * @param  array<int, int>  $ids  id загруженных, ещё не привязанных файлов
     */
    public static function attach(User $user, string $type, int $id, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        static::whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->whereNull('attachable_id')
            ->update([
                'attachable_type' => $type,
                'attachable_id' => $id,
            ]);
    }
}
