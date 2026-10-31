<?php

namespace App\Domains\Testers\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Начисление тестеру за проведённый тест.
 */
class TesterEarning extends Model
{
    protected $fillable = ['user_id', 'tier_test_id', 'tier', 'rub', 'coins'];

    protected $casts = [
        'rub' => 'integer',
        'coins' => 'integer',
    ];

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
