<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAspectBedwars extends Model
{
    protected $table = 'player_aspects_bedwars';

    protected $fillable = [
        'user_id', 'pvp', 'game_sense', 'bed_play', 'teamplay', 'building',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sum(): int
    {
        return $this->pvp + $this->game_sense + $this->bed_play
            + $this->teamplay + $this->building;
    }

    public function percent(): float
    {
        return round($this->sum() * 2, 2);
    }

    public function averageScore(): float
    {
        return round($this->sum() / 5, 2);
    }

    public function tier(): string
    {
        return match (true) {
            $this->percent() >= 90 => 'S',
            $this->percent() >= 80 => 'A',
            $this->percent() >= 70 => 'B',
            $this->percent() >= 60 => 'C',
            $this->percent() >= 50 => 'D',
            default => 'E',
        };
    }
}
