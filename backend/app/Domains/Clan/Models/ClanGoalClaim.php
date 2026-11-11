<?php

namespace App\Domains\Clan\Models;

use Illuminate\Database\Eloquent\Model;

/** Награда клана за выполненную цель недели (одна запись на цель в неделю). */
class ClanGoalClaim extends Model
{
    protected $fillable = ['clan_id', 'week', 'goal', 'claimed_by', 'coins'];
}
