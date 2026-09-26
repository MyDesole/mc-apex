<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAspectPvp extends Model
{
    protected $table = 'player_aspects_pvp';

    protected $fillable = [
        'user_id', 'block_placing', 'rotka', 'movement', 'aim', 'game_sense',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sum(): int
    {
        return $this->block_placing + $this->rotka + $this->movement
            + $this->aim + $this->game_sense;
    }

    public function percent(): float
    {
        return round($this->sum(), 2);
    }

    public function averageScore(): float
    {
        return round($this->sum() / 5, 2);
    }

    public function tier(): string
    {
        return match (true) {
            $this->percent() >= 71 => 'A',
            $this->percent() >= 56 => 'B',
            $this->percent() >= 41 => 'C',
            $this->percent() >= 21 => 'D',
            default => 'E',
        };
    }
}
