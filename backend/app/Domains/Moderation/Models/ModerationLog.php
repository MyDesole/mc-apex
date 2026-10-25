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
        'user.rename' => 'Смена ника',
        'user.avatar_removed' => 'Удалён аватар',
        'user.cover_removed' => 'Удалена обложка',
        'user.profile_cleared' => 'Очищен профиль',
        'user.note' => 'Заметка',
        'user.password_reset_sent' => 'Письмо для сброса пароля',
        'user.password_set' => 'Временный пароль',
        'user.email_verified' => 'Почта подтверждена',
        'user.sessions_revoked' => 'Выход со всех устройств',
        'user.minecraft_unlinked' => 'Отвязан Minecraft',
        'user.item_gift' => 'Подарен предмет',
        'control.update' => 'Пульт: настройки',
        'control.giveaway' => 'Раздача',
        'control.promo' => 'Новый промокод',
        'bridge.submission_updated' => 'Бридж: правка оценок',
        'bridge.submission_deleted' => 'Бридж: заявка удалена',
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
