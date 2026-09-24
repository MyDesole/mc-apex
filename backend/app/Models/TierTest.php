<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierTest extends Model
{
    protected $fillable = [
        'user_id', 'tester_id', 'claimed_by', 'claimed_at',
        'mode', 'status', 'scheduled_at', 'completed_at',
        'result_tier', 'result_score', 'aspects', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'aspects' => 'array',
        'result_score' => 'decimal:2',
    ];

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }
}
