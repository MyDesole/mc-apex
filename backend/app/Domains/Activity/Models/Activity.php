<?php

namespace App\Domains\Activity\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Событие в ленте «Сейчас на APEX».
 *
 * @property int $id
 * @property string $type
 * @property ?int $actor_id
 * @property array $payload
 */
class Activity extends Model
{
    public const TIER_ASSIGNED = 'tier.assigned';
    public const ACHIEVEMENT_EARNED = 'achievement.earned';
    public const CLAN_WAR_FINISHED = 'clan_war.finished';
    public const TOURNAMENT_WON = 'tournament.won';
    public const CLAN_CREATED = 'clan.created';
    public const MINECRAFT_LINKED = 'minecraft.linked';

    protected $fillable = ['type', 'actor_id', 'payload', 'created_at'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
