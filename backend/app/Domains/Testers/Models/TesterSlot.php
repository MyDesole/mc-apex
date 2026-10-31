<?php

namespace App\Domains\Testers\Models;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Свободный слот в календаре тестера.
 */
class TesterSlot extends Model
{
    public const OPEN = 'open';
    public const BOOKED = 'booked';
    public const CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'starts_at', 'ends_at', 'status', 'note', 'tier_test_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tierTest(): BelongsTo
    {
        return $this->belongsTo(TierTest::class, 'tier_test_id');
    }
}
