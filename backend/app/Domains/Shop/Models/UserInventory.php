<?php

namespace App\Domains\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class UserInventory extends Model
{
    protected $table = 'user_inventory';

    protected $fillable = [
        'user_id', 'shop_item_id', 'quantity', 'equipped_at', 'acquired_at', 'gifted_by',
    ];

    protected $casts = [
        'equipped_at' => 'datetime',
        'acquired_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }

    public function isEquipped(): bool
    {
        return $this->equipped_at !== null;
    }
}
