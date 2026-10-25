<?php

namespace App\Domains\Seasons\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonResult extends Model
{
    protected $fillable = ['season_id', 'user_id', 'rank', 'tier', 'tier_score', 'rating_points'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
