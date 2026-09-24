<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClanEventComment extends Model
{
    protected $fillable = [
        'clan_event_id',
        'user_id',
        'parent_id',
        'body',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClanEvent::class, 'clan_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest();
    }
}
