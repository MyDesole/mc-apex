<?php

namespace App\Domains\Control\Models;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code', 'coins', 'shop_item_id', 'max_uses', 'uses',
        'expires_at', 'is_active', 'note', 'created_by',
    ];

    protected $casts = [
        'coins' => 'integer',
        'max_uses' => 'integer',
        'uses' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
