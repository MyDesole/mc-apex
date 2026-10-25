<?php

namespace App\Domains\Tiers\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Апелляция игрока на результат тир-теста.
 */
class TierTestAppeal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tier_test_id', 'user_id', 'reason', 'status',
        'reviewer_id', 'resolution', 'revised_test_id', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function tierTest(): BelongsTo
    {
        return $this->belongsTo(TierTest::class);
    }

    public function revisedTest(): BelongsTo
    {
        return $this->belongsTo(TierTest::class, 'revised_test_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
