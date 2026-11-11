<?php

namespace App\Domains\Clan\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Приглашение игрока в клан от лидера или офицера.
 *
 * В отличие от заявки, приглашение принимается бесплатно: клан сам позвал игрока,
 * плата за вступление и «закрытый» набор к нему не применяются.
 */
class ClanInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    /** Сколько дней приглашение ждёт ответа. */
    public const TTL_DAYS = 7;

    protected $fillable = ['clan_id', 'user_id', 'invited_by', 'message', 'status', 'expires_at', 'responded_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_PENDING && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
