<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClanStatsLog extends Model
{
    protected $fillable = [
        'clan_id', 'user_id', 'wins_delta', 'losses_delta', 'reason',
    ];
}
