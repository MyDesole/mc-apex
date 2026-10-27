<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Реакция на сообщение: эмодзи от игрока.
 */
class MessageReaction extends Model
{
    /** Реакции, которые можно поставить. */
    public const EMOJI = ['❤️', '👍', '😂', '😮', '😢', '🔥', '💀', '👑'];

    protected $fillable = ['message_id', 'user_id', 'emoji'];
}
