<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id', 'user_id', 'original_name', 'path', 'mime', 'size', 'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'size' => 'integer',
    ];

    protected $appends = ['url', 'human_size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
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
}
