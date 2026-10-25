<?php

namespace App\Domains\Clan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class ClanMember extends Model
{
    protected $fillable = [
        'clan_id', 'user_id', 'role', 'permissions',
        'title', 'contribution', 'joined_at',
        'promoted_at', 'promoted_by',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'promoted_at' => 'datetime',
        'permissions' => 'array',
    ];

    /**
     * Вклад участника в силу клана — та же доля очков рейтинга, что
     * складывается в Clan::power (RatingService::CLAN_SHARE). Колонка
     * contribution больше никем не пишется, поэтому считаем на лету.
     */
    public function getContributionAttribute(): int
    {
        $user = $this->user;

        if (! $user || $user->isStaff()) {
            return 0;
        }

        return (int) floor((int) $user->rating_points * \App\Domains\Players\Services\RatingService::CLAN_SHARE);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    /**
     * Проверка права: либо роль выше, либо кастомное право.
     */
    public function can(string $permission): bool
    {
        // owner может всё
        if ($this->role === 'leader') return true;

        $rolePermissions = [
            'officer' => ['news', 'forum', 'applications', 'wars', 'resources'],
            'member' => [],
        ];

        $rolePerms = $rolePermissions[$this->role] ?? [];
        $customPerms = $this->permissions ?? [];

        return in_array($permission, $rolePerms)
            || in_array($permission, $customPerms);
    }
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
