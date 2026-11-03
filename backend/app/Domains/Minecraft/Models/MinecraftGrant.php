<?php

namespace App\Domains\Minecraft\Models;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Выдача на игровом сервере: список консольных команд для плагина. */
class MinecraftGrant extends Model
{
    public const PENDING = 'pending';
    public const DONE = 'done';
    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'shop_item_id', 'minecraft_username', 'commands',
        'status', 'attempts', 'error', 'delivered_at',
    ];

    protected $casts = [
        'commands' => 'array',
        'attempts' => 'integer',
        'delivered_at' => 'datetime',
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
