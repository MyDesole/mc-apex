<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumReply extends Model
{
    protected $fillable = [
        'topic_id', 'author_id', 'parent_id', 'body',
        'likes_count', 'edited_at', 'deleted_by', 'deleted_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'likes_count' => 'integer',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ForumAttachment::class, 'attachable_id')
            ->where('attachable_type', 'reply');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Максимальная глубина вложенности ответов.
     * Ограничение защищает от бесконечных цепочек и «лесенки» в интерфейсе.
     */
    public const MAX_DEPTH = 8;

    /**
     * Уровень вложенности: 0 — ответ на тему, 1 — ответ на ответ и т.д.
     */
    public function depth(): int
    {
        $depth = 0;
        $current = $this;

        // Идём по цепочке родителей вверх (с защитой от цикла)
        while ($current->parent_id && $depth < 64) {
            $current = static::find($current->parent_id);

            if (! $current) {
                break;
            }

            $depth++;
        }

        return $depth;
    }

    /**
     * Все потомки (включая вложенные уровни) — для каскадного удаления.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $stack = [$this->id];

        while ($stack) {
            $parentIds = array_pop($stack);

            $children = static::where('parent_id', $parentIds)->pluck('id')->all();

            foreach ($children as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }
}
