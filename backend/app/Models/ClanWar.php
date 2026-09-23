<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanWar extends Model
{
    protected $fillable = [
        'challenger_clan_id', 'opponent_clan_id', 'created_by',
        'status', 'scheduled_at',
        'challenger_score', 'opponent_score',
        'winner_clan_id', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'challenger_clan_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'opponent_clan_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'winner_clan_id');
    }
}
