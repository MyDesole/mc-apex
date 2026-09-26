<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentWin extends Model
{
    protected $fillable = [
        'user_id', 'tournament_id', 'min_tier', 'max_tier',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Уровень турнира, в котором одержана победа.
     * A — если диапазон B..A.
     * S — если диапазон A..S.
     * null — иначе.
     */
    public function getLevelAttribute(): ?string
    {
        if ($this->min_tier === 'B' && $this->max_tier === 'A') return 'A';
        if ($this->min_tier === 'A' && $this->max_tier === 'S') return 'S';
        return null;
    }
}
