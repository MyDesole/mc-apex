<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clan extends Model
{
    protected $fillable = [
        'name', 'tag', 'description', 'avatar', 'cover_path',
        'banner_color', 'leader_id', 'power', 'wins', 'losses',
        'is_open', 'is_highlighted', 'socials', 'max_members',
        'is_banned', 'ban_reason',

    ];


    protected $casts = [
        'is_open' => 'boolean',
        'is_highlighted' => 'boolean',
        'is_banned' => 'boolean',
        'socials' => 'array',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClanMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clan_members')
            ->withPivot(['role', 'contribution', 'joined_at'])
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClanEvent::class)->latest('starts_at');
    }

    public function wars(): HasMany
    {
        return $this->hasMany(ClanWar::class, 'challenger_clan_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ClanApplication::class)->where('status', 'pending');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? asset('storage/' . $this->cover_path) : null;
    }

    protected $appends = ['avatar_url', 'cover_url'];


    public function recalculatePower(): void
    {
        $this->power = $this->members()->sum('contribution')
            + ($this->wins * 100)
            - ($this->losses * 50);

        $this->save();
    }

    public function isMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function isLeader(int $userId): bool
    {
        return $this->leader_id === $userId;
    }
}
