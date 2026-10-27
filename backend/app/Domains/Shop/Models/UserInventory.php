<?php

namespace App\Domains\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;
use App\Domains\Players\Support\PetTraits;

class UserInventory extends Model
{
    protected $table = 'user_inventory';

    protected $fillable = [
        'user_id', 'shop_item_id', 'quantity', 'equipped_at', 'acquired_at', 'gifted_by', 'pet_name',
    ];

    protected $casts = [
        'equipped_at' => 'datetime',
        'acquired_at' => 'datetime',
        'quantity' => 'integer',
        'pet_traits' => 'array',
        'pet_serial' => 'integer',
    ];

    /**
     * Питомец «чеканится» при появлении в инвентаре — откуда бы ни пришёл
     * (покупка, кейс, набор, админ): случайные черты и номер экземпляра.
     */
    protected static function booted(): void
    {
        static::creating(function (UserInventory $row) {
            if ($row->pet_traits) {
                return;
            }

            $item = ShopItem::find($row->shop_item_id);

            if ($item?->type !== ShopItem::TYPE_PROFILE_PET) {
                return;
            }

            $row->pet_traits = PetTraits::roll();
            $row->pet_serial = (int) static::where('shop_item_id', $row->shop_item_id)->max('pet_serial') + 1;
        });
    }

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
