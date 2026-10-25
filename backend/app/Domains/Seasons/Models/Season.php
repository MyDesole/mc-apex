<?php

namespace App\Domains\Seasons\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Рейтинговый сезон. Открыт, пока ends_at пуст.
 */
class Season extends Model
{
    protected $fillable = ['number', 'name', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(SeasonResult::class);
    }

    public function isActive(): bool
    {
        return $this->ends_at === null;
    }
}
