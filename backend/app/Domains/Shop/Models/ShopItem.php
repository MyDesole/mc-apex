<?php

namespace App\Domains\Shop\Models;

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

    /** Сцена за 3D-скином в профиле. */
    public const TYPE_SKIN_BACKDROP = 'skin_backdrop';

    /** Оформление карточки игрока (PNG). */
    public const TYPE_CARD_THEME = 'card_theme';

    /** Макет страницы профиля: расстановка блоков, цвета, фон, шрифты. */
    public const TYPE_PROFILE_LAYOUT = 'profile_layout';

    // Набор: несколько предметов одной темы со скидкой (metadata.items — slug'и)
    public const TYPE_BUNDLE = 'bundle';

    // Видно по всему сайту: эффект ника; гостям профиля: появление и след курсора
    public const TYPE_NAME_EFFECT = 'name_effect';
    public const TYPE_PROFILE_ENTRANCE = 'profile_entrance';
    public const TYPE_CURSOR_TRAIL = 'cursor_trail';

    // Питомец в шапке профиля, оформление своих сообщений в чате, титул под ником
    public const TYPE_PROFILE_PET = 'profile_pet';
    public const TYPE_CHAT_BUBBLE = 'chat_bubble';
    public const TYPE_PROFILE_TITLE = 'profile_title';

    /**
     * Типы, которые при надевании пишутся в поле профиля пользователя.
     */
    public const EQUIPPABLE = [
        self::TYPE_AVATAR_FRAME,
        self::TYPE_PROFILE_EFFECT,
        self::TYPE_ACCENT_COLOR,
        self::TYPE_SKIN_BACKDROP,
        self::TYPE_CARD_THEME,
        self::TYPE_PROFILE_LAYOUT,
        self::TYPE_BADGE,
        self::TYPE_NAME_EFFECT,
        self::TYPE_PROFILE_ENTRANCE,
        self::TYPE_CURSOR_TRAIL,
        self::TYPE_PROFILE_PET,
        self::TYPE_CHAT_BUBBLE,
        self::TYPE_PROFILE_TITLE,
        // Оформление подсветки клана применяется к клану лидера
        self::TYPE_CLAN_HIGHLIGHT_STYLE,
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

    public function isBundle(): bool
    {
        return $this->type === self::TYPE_BUNDLE;
    }

    /** Личная косметика: надевается в профиль игрока (не клановая). */
    public function isPersonalCosmetic(): bool
    {
        return $this->isEquippable() && ! $this->isClanItem();
    }

    /** Предмет для клана: покупает лидер, можно оплатить из казны. */
    public function isClanItem(): bool
    {
        return in_array($this->type, [self::TYPE_CLAN_HIGHLIGHT, self::TYPE_CLAN_HIGHLIGHT_STYLE], true);
    }
}
