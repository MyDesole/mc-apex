<?php

namespace App\Domains\Tournaments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;

class TournamentParticipant extends Model
{
    protected $fillable = [
        'tournament_id', 'user_id', 'clan_id',
        'seed', 'status',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->clan) return "[{$this->clan->tag}] {$this->clan->name}";
        return $this->user?->username ?? '—';
    }
}
