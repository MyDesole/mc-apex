<?php

namespace App\Domains\Moderation\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись журнала: кто из персонала что сделал и с кем.
 * Пишется через ModerationLog::record() из сервисов и контроллеров админки.
 */
class ModerationLog extends Model
{
    public const UPDATED_AT = null;

    /** Подписи действий для журнала в админке. */
    public const LABELS = [
        'user.ban' => 'Бан',
        'user.unban' => 'Разбан',
        'user.warn' => 'Предупреждение',
        'user.role' => 'Смена роли',
        'user.verify' => 'Верификация',
        'user.unverify' => 'Снятие верификации',
        'user.coins' => 'Начисление ApexCoin',
        'report.dismiss' => 'Жалоба отклонена',
        'report.resolve' => 'Жалоба рассмотрена',
        'content.remove' => 'Удаление контента',
    ];

    protected $fillable = [
        'actor_id', 'action', 'target_user_id', 'subject_type', 'subject_id', 'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(
        ?User $actor,
        string $action,
        ?int $targetUserId = null,
        array $details = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): self {
        return static::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details ?: null,
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
