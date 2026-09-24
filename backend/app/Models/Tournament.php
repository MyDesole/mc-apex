<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'banner',
        'type', 'format', 'status',
        'prize_pool', 'prize_currency', 'prize_description',
        'min_tier', 'max_tier', 'max_participants',
        'registration_starts_at', 'registration_ends_at',
        'starts_at', 'ends_at', 'created_by',
    ];

    protected $casts = [
        'prize_pool' => 'decimal:2',
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('position');
    }

    public function approvedParticipants(): HasMany
    {
        return $this->participants()->where('status', 'approved');
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner ? asset('storage/' . $this->banner) : null;
    }

    protected $appends = ['banner_url'];

    public function isRegistrationOpen(): bool
    {
        if ($this->status !== 'registration') return false;

        $now = now();
        if ($this->registration_starts_at && $now->lt($this->registration_starts_at)) return false;
        if ($this->registration_ends_at && $now->gt($this->registration_ends_at)) return false;

        return true;
    }
}
