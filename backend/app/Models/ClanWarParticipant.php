<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanWarParticipant extends Model
{
    protected $fillable = ['clan_war_id', 'user_id', 'clan_id', 'joined_at'];

    protected $casts = ['joined_at' => 'datetime'];

    public function war(): BelongsTo
    {
        return $this->belongsTo(ClanWar::class, 'clan_war_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
}
