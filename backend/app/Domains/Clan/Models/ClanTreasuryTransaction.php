<?php

namespace App\Domains\Clan\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Движение по казне клана: взнос участника или покупка лидера.
 */
class ClanTreasuryTransaction extends Model
{
    public const TYPE_DONATION = 'donation';
    public const TYPE_PURCHASE = 'purchase';
    // Бонус от сайта (отметки участников), а не взнос игрока
    public const TYPE_REWARD = 'reward';

    protected $fillable = ['clan_id', 'user_id', 'amount', 'balance_after', 'type', 'description'];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
