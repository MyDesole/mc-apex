<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username', 'email', 'password', 'avatar', 'cover_path',
        'banner_color', 'bio', 'tier', 'tier_score', 'socials',
        'role', 'is_banned', 'ban_reason', 'banned_until', 'banned_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tier_score' => 'decimal:2',
        'socials' => 'array',
        'is_banned' => 'boolean',
        'banned_until' => 'datetime',

    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin']);
    }

    public function isTester(): bool
    {
        return in_array($this->role, ['tester', 'admin']);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isBanned(): bool
    {
        if (!$this->is_banned) return false;

        if ($this->banned_until && $this->banned_until->isPast()) {
            return false;
        }

        return true;
    }

    protected $appends = ['avatar_url', 'cover_url', 'clan_tag'];

    public function getClanTagAttribute(): ?string
    {
        if (!$this->relationLoaded('clanMember')) return null;
        return $this->clanMember?->clan?->tag;
    }

    public function getClanColorAttribute(): ?string
    {
        if (!$this->relationLoaded('clanMember')) return null;
        return $this->clanMember?->clan?->banner_color;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
    public function achievements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function hasAchievement(string $code): bool
    {
        return $this->achievements()->where('code', $code)->exists();
    }

    public function achievementPoints(): int
    {
        return (int) $this->achievements()->sum('points');
    }
    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? asset('storage/' . $this->cover_path) : null;
    }

    public function aspects(): HasMany
    {
        return $this->hasMany(PlayerAspect::class);
    }

    public function aspect(string $mode): ?PlayerAspect
    {
        return $this->aspects->firstWhere('mode', $mode);
    }

    public function tierTests(): HasMany
    {
        return $this->hasMany(TierTest::class);
    }

    public function friends(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id')
            ->where('status', 'accepted');
    }

    public function friendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id')
            ->where('status', 'pending');
    }

    public function clanMember(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClanMember::class);
    }

    public function clan(): ?Clan
    {
        return $this->clanMember?->clan;
    }

    public function isFriendsWith(int $userId): bool
    {
        return Friendship::where(function ($q) use ($userId) {
            $q->where('user_id', $this->id)->where('friend_id', $userId);
        })->orWhere(function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('friend_id', $this->id);
        })->where('status', 'accepted')->exists();
    }
}
