<?php

namespace App\Domains\Project\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись чейнджлога: что изменилось на сайте. У каждой — своя ссылка
 * /changelog/{slug}, её удобно отправить в Telegram-канал.
 */
class ChangelogEntry extends Model
{
    public const CATEGORIES = [
        'feature' => 'Новое',
        'improvement' => 'Улучшение',
        'fix' => 'Исправление',
    ];

    protected $fillable = [
        'title', 'slug', 'version', 'category', 'body',
        'is_published', 'published_at', 'author_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Опубликованные и не отложенные на будущее. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
