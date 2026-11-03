<?php

namespace App\Domains\Minecraft\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Итог одной дуэли Alley 1 на 1, присланный плагином сервера. */
class MinecraftDuel extends Model
{
    protected $fillable = [
        'match_key', 'kit', 'winner_name', 'loser_name', 'winner_user_id',
        'loser_user_id', 'duration_seconds', 'ranked', 'played_at',
    ];

    protected $casts = [
        'ranked' => 'boolean',
        'duration_seconds' => 'integer',
        'played_at' => 'datetime',
    ];

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loser_user_id');
    }
}
