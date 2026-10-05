<?php

namespace App\Domains\Bridge\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка игрока на вид бриджа: видео, оценки тестера и статус.
 *
 * Пока статус declared, вид в профиле серый. После подтверждения вид
 * становится ярким и попадает в топ бриджеров вместе с оценками.
 */
class UserBridgeTechnique extends Model
{
    protected $table = 'user_bridge_techniques';

    public const STATUS_DECLARED = 'declared';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    /** Максимум владения видом. */
    public const MAX_SCORE = 10;

    /** Максимум по каждому аспекту. */
    public const MAX_ASPECT = 100;

    protected $fillable = [
        'user_id', 'technique_id', 'status', 'video_url', 'video_path',
        'stability', 'speed', 'difficulty', 'score',
        'review_notes', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'stability' => 'integer',
        'speed' => 'integer',
        'difficulty' => 'integer',
        'score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function technique(): BelongsTo
    {
        return $this->belongsTo(BridgeTechnique::class, 'technique_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /** Сумма аспектов: общая статистика вида (0–300). */
    public function getTotalAttribute(): int
    {
        return (int) $this->stability + (int) $this->speed + (int) $this->difficulty;
    }
}
