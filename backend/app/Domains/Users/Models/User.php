<?php

namespace App\Domains\Users\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Domains\Users\Concerns\HasRoles;
use App\Domains\Achievements\Models\Achievement;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Friends\Models\Friendship;
use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Clan\Models\Clan;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'username', 'email', 'email_verified_at', 'password', 'avatar', 'cover_path',
        'banner_color', 'bio', 'tier', 'tier_score', 'socials',
        'role', 'is_banned', 'ban_reason', 'banned_until', 'banned_by',
        'avatar_frame', 'profile_effect', 'accent_color',
        'status', 'quote', 'favorite_clan_id',
        'featured_achievements', 'profile_visibility', 'card_background',
        'favorite_modes', 'discord_tag',
        'profile_mode', 'bridge_rank_id', 'bridge_rank_by', 'bridge_rank_at',
        'apex_coins', 'apex_coins_spent', 'equipped_badges',
        'referral_code', 'referred_by',
        'is_verified', 'verified_reason', 'clan_joined_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',

    ];

    public function recalcTierFromAspects(): void
    {
        $pvp = $this->aspectPvp;
        $bw = $this->aspectBedwars;

        $best = max($pvp?->percent() ?? 0, $bw?->percent() ?? 0);

        $this->tier_score = $best;

        if (!in_array($this->tier, ['S', 'S+'], true)) {
            $this->tier = match (true) {
                $best >= 71 => 'A',
                $best >= 56 => 'B',
                $best >= 41 => 'C',
                $best >= 21 => 'D',
                default => 'E',
            };
        }

        $this->save();
    }

    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function sentMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Domains\Auth\Notifications\ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Domains\Auth\Notifications\VerifyEmailNotification());
    }

    public function aspectPvp(): HasOne
    {
        return $this->hasOne(PlayerAspectPvp::class);
    }

    public function aspectBedwars(): HasOne
    {
        return $this->hasOne(PlayerAspectBedwars::class);
    }

    /**
     * Универсальный доступ к аспектам по режиму.
     */
    public function aspect(string $mode)
    {
        return $mode === 'pvp' ? $this->aspectPvp : $this->aspectBedwars;
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tier_score' => 'decimal:2',
        'socials' => 'array',
        'is_banned' => 'boolean',
        'banned_until' => 'datetime',
        'favorite_modes' => 'array',
        'bridge_rank_at' => 'datetime',
        'featured_achievements' => 'array',
        'is_verified' => 'boolean',
        'apex_coins' => 'integer',
        'apex_coins_spent' => 'integer',
        'equipped_badges' => 'array',
        'referred_by' => 'integer',
        'clan_joined_at' => 'datetime',
        'last_seen_at' => 'datetime',

    ];


    /**
     * Операции с ApexCoin.
     */
    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    /**
     * Купленная косметика.
     */
    public function inventory(): HasMany
    {
        return $this->hasMany(UserInventory::class);
    }

    /**
     * Надетые бейджи из магазина.
     */
    public function getBadgesAttribute(): array
    {
        return $this->equipped_badges ?? [];
    }
    public function getCardBackgroundUrlAttribute(): ?string
    {
        return $this->card_background ? asset('storage/' . $this->card_background) : null;
    }
    // Роли (isAdmin/isModerator/isTester/isMedia/isStaff/hasRole) — в трейте HasRoles

    public function isBanned(): bool
    {
        if (!$this->is_banned) return false;

        if ($this->banned_until && $this->banned_until->isPast()) {
            return false;
        }

        return true;
    }

    public function getFeaturedAchievementsListAttribute()
    {
        if (!$this->featured_achievements) return collect();
        return \App\Domains\Achievements\Models\Achievement::whereIn('id', $this->featured_achievements)->get();
    }


    public function favoriteClan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\Clan\Models\Clan::class, 'favorite_clan_id');
    }

    public function featuredAchievements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Achievements\Models\Achievement::class,
            'user_featured_achievements',
            'user_id',
            'achievement_id'
        );
    }

    protected $appends = [
        'avatar_url', 'cover_url', 'clan_tag', 'clan_color',
        'card_background_url', 'days_on_platform',
        'featured_achievements_list',
        'friends_all',
    ];
    public function getAspectsAttribute()
    {
        return [
            'pvp' => $this->aspectPvp,
            'bedwars' => $this->aspectBedwars,
        ];
    }

    public function recommendationsReceived(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Players\Models\ProfileRecommendation::class, 'target_id')
            ->where('is_hidden', false)
            ->latest();
    }

    public function recommendationsWritten(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Players\Models\ProfileRecommendation::class, 'author_id')
            ->latest();
    }

    public function getFriendsAllAttribute(): \Illuminate\Support\Collection
    {
        $direct = $this->relationLoaded('friendsList')
            ? $this->getRelation('friendsList')
            : collect();

        $reverse = $this->relationLoaded('friendsOf')
            ? $this->getRelation('friendsOf')
            : collect();

        return $direct
            ->merge($reverse)
            ->unique('id')
            ->values();
    }

    public function getClanTagAttribute(): ?string
    {
        if (!$this->relationLoaded('clanMember')) return null;
        return $this->clanMember?->clan?->tag;
    }

    public function getDaysOnPlatformAttribute(): int
    {
        if (!$this->created_at) return 0;

        return (int) $this->created_at->diffInDays(now());
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




    public function tierTests(): HasMany
    {
        return $this->hasMany(TierTest::class);
    }

    public function friends(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id')
            ->where('status', 'accepted');
    }

    public function friendsList(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'friendships',
            'user_id',
            'friend_id'
        )
            ->wherePivot('status', 'accepted')
            ->withPivot('status', 'created_at');
    }

    public function friendsOf(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'friendships',
            'friend_id',
            'user_id'
        )
            ->wherePivot('status', 'accepted')
            ->withPivot('status', 'created_at');
    }

    public function friendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id')
            ->where('status', 'pending');
    }

    /** Звание бриджера: выдаёт бридж-тестер вручную. */
    public function bridgeRank(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\Bridge\Models\BridgeRank::class, 'bridge_rank_id');
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
        // Статус должен применяться к ОБЕИМ веткам: без группировки
        // AND/OR давал «(A AND B) OR (B AND A AND accepted)», и заявка
        // в друзья без подтверждения считалась дружбой.
        return Friendship::where('status', 'accepted')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $this->id)->where('friend_id', $userId);
            })
            ->orWhere(function ($q) use ($userId) {
                $q->where('status', 'accepted')
                    ->where('user_id', $userId)
                    ->where('friend_id', $this->id);
            })
            ->exists();
    }
}
