<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoinTransaction extends Model
{
    public const SOURCE_TIER_TEST = 'tier_test';
    public const SOURCE_ACHIEVEMENT = 'achievement';
    public const SOURCE_DAILY_BONUS = 'daily_bonus';
    public const SOURCE_GIFT_IN = 'gift_in';
    public const SOURCE_GIFT_OUT = 'gift_out';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_CLAN_FEE = 'clan_fee';
    public const SOURCE_OTHER = 'other';

    protected $fillable = [
        'user_id', 'amount', 'balance_after', 'source', 'description',
        'reference_type', 'reference_id', 'idempotency_key', 'actor_id', 'meta',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
