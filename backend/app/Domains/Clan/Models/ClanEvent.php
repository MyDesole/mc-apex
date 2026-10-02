<?php

namespace App\Domains\Clan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Users\Models\User;

class ClanEvent extends Model
{
    protected $fillable = ['clan_id', 'author_id', 'type', 'title', 'body', 'starts_at'];

    protected $casts = [
        'starts_at' => 'datetime',
    ];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
    public function comments(): HasMany
    {
        return $this->hasMany(ClanEventComment::class)->latest();
    }
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
