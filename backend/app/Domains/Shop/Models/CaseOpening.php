<?php

namespace App\Domains\Shop\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одно открытие кейса: что выпало и не было ли это повтором.
 */
class CaseOpening extends Model
{
    protected $fillable = ['user_id', 'case_slug', 'shop_item_id', 'outcome', 'price', 'coins', 'duplicate'];

    protected $casts = [
        'price' => 'integer',
        'coins' => 'integer',
        'duplicate' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }
}
