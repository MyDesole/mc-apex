<?php

namespace App\Domains\Testers\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Настройки тестера: пока только блокировка календаря.
 */
class TesterProfile extends Model
{
    protected $fillable = ['user_id', 'blocked_until', 'blocked_reason'];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
