<?php

namespace App\Domains\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;
use App\Support\HtmlSanitizer;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'cover', 'type',
        'is_pinned', 'is_published', 'author_id', 'published_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Текст из визуального редактора — HTML по белому списку. Старые
     * новости — обычный текст: его не трогаем, страница покажет абзацами.
     */
    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value !== null && preg_match('/<[a-z][\s\S]*>/i', $value)
            ? HtmlSanitizer::clean($value)
            : $value;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover ? asset('storage/' . $this->cover) : null;
    }

    protected $appends = ['cover_url'];
}
