<?php

namespace App\Domains\Forum\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Users\Models\User;

class ForumCategory extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'icon', 'color',
        'sort_order', 'post_policy', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Может ли игрок создавать темы в этом разделе.
     */
    public function allowsPosting(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return match ($this->post_policy) {
            'verified' => (bool) $user->is_verified,
            'staff' => $user->isModerator() || $user->isTester(),
            default => true,
        };
    }

    public function policyLabel(): string
    {
        return match ($this->post_policy) {
            'verified' => 'Только верифицированные',
            'staff' => 'Только персонал',
            default => 'Все игроки',
        };
    }
}
