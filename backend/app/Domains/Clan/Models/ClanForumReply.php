<?php

namespace App\Domains\Clan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class ClanForumReply extends Model
{
    protected $fillable = [
        'topic_id', 'author_id', 'parent_id', 'body',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ClanForumTopic::class, 'topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
