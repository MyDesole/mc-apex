<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
