<?php

namespace App\Domains\Achievements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class UserAchievement extends Model
{
    protected $fillable = ['user_id', 'achievement_id', 'earned_at'];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
