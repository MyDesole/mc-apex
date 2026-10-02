<?php

namespace App\Domains\Achievements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Domains\Users\Models\User;

class Achievement extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'icon',
        'color', 'rarity', 'points',
        'coin_reward',
        'is_system', 'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'coin_reward' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
