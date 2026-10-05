<?php

namespace App\Domains\Tiers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class TierTest extends Model
{
    protected $fillable = [
        'user_id', 'tester_id', 'claimed_by', 'claimed_at',
        'contact_type', 'contact_value', 'preferred_time',
        'mode', 'expected_tier', 'status', 'scheduled_at', 'completed_at',
        'result_tier', 'result_score', 'aspects', 'notes',
        'is_priority', 'priority_purchased_at', 'priority_price_paid', 'priority_weight',
        'block_placing', 'rotka', 'movement', 'building', 'ppl', 'bed_play',   // ← добавили
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'aspects' => 'array',
        'is_priority' => 'boolean',
        'priority_purchased_at' => 'datetime',
        'priority_price_paid' => 'integer',
        'priority_weight' => 'integer',
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
