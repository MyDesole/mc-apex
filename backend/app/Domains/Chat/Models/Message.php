<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Users\Models\User;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'forwarded_from_user_id',
        'kind',
        'body',
        'meta',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'meta' => 'array',
    ];

    /** Обычное сообщение. */
    public const KIND_TEXT = 'text';

    /** Подарок монет: сумма в meta. */
    public const KIND_GIFT = 'gift';

    /** Служебная отметка беседы: кто вошёл, вышел, переименовал. */
    public const KIND_SYSTEM = 'system';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_from_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Вложения сообщения: картинки и файлы.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }
}
