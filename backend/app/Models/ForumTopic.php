<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumTopic extends Model
{
    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'body',
        'is_pinned', 'is_locked', 'last_reply_user_id', 'last_reply_at',
        'views', 'replies_count', 'likes_count', 'deleted_by', 'deleted_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'last_reply_at' => 'datetime',
        'deleted_at' => 'datetime',
        'views' => 'integer',
        'replies_count' => 'integer',
        'likes_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastReplyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reply_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ForumAttachment::class, 'attachable_id')
            ->where('attachable_type', 'topic');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
