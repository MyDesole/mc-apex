<?php

namespace App\Domains\Clan\Models;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Клановый предмет: оформление подсветки, купленное для клана.
 * Принадлежит клану, а не лидеру, поэтому переживает смену лидера.
 */
class ClanInventory extends Model
{
    protected $table = 'clan_inventory';

    protected $fillable = ['clan_id', 'shop_item_id', 'purchased_by', 'equipped_at'];

    protected $casts = [
        'equipped_at' => 'datetime',
    ];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function isEquipped(): bool
    {
        return $this->equipped_at !== null;
    }
}
