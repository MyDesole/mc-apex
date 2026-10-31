<?php

namespace App\Domains\Testers\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запрос тестера на выплату заработанного.
 */
class TesterPayout extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'rub', 'coins', 'status', 'decided_by', 'decided_at', 'comment',
    ];

    protected $casts = [
        'rub' => 'integer',
        'coins' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
