<?php

namespace App\Domains\Moderation\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Жалоба игрока на профиль, сообщение или пост форума.
 */
class Report extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** На что можно пожаловаться. */
    public const SUBJECTS = ['user', 'message', 'forum_topic', 'forum_reply'];

    public const REASONS = [
        'cheating' => 'Читы',
        'insult' => 'Оскорбления',
        'spam' => 'Спам или реклама',
        'scam' => 'Мошенничество',
        'nickname' => 'Ник или аватар',
        'other' => 'Другое',
    ];

    /** Решения модератора. */
    public const ACTIONS = ['dismiss', 'warn', 'ban', 'remove_content'];

    protected $fillable = [
        'reporter_id', 'target_user_id', 'subject_type', 'subject_id',
        'reason', 'comment', 'snapshot', 'status', 'resolution',
        'moderator_id', 'moderator_note', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
