<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClanForumTopic extends Model
{
    protected $fillable = [
        'clan_id', 'author_id', 'title', 'body',
        'is_pinned', 'is_locked', 'views',
        'replies_count', 'last_reply_at', 'last_reply_user_id',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'last_reply_at' => 'datetime',
    ];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastReplyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reply_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ClanForumReply::class, 'topic_id');
    }
}
