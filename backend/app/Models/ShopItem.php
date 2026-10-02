<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopItem extends Model
{
    public const TYPE_AVATAR_FRAME = 'avatar_frame';
    public const TYPE_PROFILE_EFFECT = 'profile_effect';
    public const TYPE_ACCENT_COLOR = 'accent_color';
    public const TYPE_CARD_BACKGROUND = 'card_background';
    public const TYPE_BADGE = 'badge';
    public const TYPE_TIER_PRIORITY = 'tier_priority';
    public const TYPE_COIN_BUNDLE = 'coin_bundle';
    public const TYPE_CLAN_HIGHLIGHT = 'clan_highlight';
    public const TYPE_CLAN_HIGHLIGHT_STYLE = 'clan_highlight_style';

    /**
     * Типы, которые при надевании пишутся в поле профиля пользователя.
     */
    public const EQUIPPABLE = [
        self::TYPE_AVATAR_FRAME,
        self::TYPE_PROFILE_EFFECT,
        self::TYPE_ACCENT_COLOR,
        self::TYPE_BADGE,
    ];

    protected $fillable = [
        'slug', 'name', 'description', 'type', 'rarity', 'effect_value',
        'price', 'is_active', 'sort_order', 'is_consumable', 'is_repeatable',
        'max_quantity', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_consumable' => 'boolean',
        'is_repeatable' => 'boolean',
        'price' => 'integer',
        'sort_order' => 'integer',
        'max_quantity' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['icon', 'color'];

    public function inventory(): HasMany
    {
        return $this->hasMany(UserInventory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getIconAttribute(): ?string
    {
        return $this->metadata['icon'] ?? null;
    }

    public function getColorAttribute(): ?string
    {
        return $this->metadata['color'] ?? null;
    }

    /**
     * Можно ли купить предмет повторно (несколько копий / расходник).
     */
    public function isStackable(): bool
    {
        return $this->is_consumable || $this->is_repeatable;
    }

    public function isEquippable(): bool
    {
        return in_array($this->type, self::EQUIPPABLE, true);
    }
}
