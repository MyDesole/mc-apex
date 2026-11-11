<?php

namespace App\Domains\Tiers\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сообщение в чате заявки на тир-тест (тестируемый ↔ тестер).
 */
class TierTestMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tier_test_id', 'user_id', 'body', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tierTest(): BelongsTo
    {
        return $this->belongsTo(TierTest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
