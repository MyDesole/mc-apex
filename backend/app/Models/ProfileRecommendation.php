<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileRecommendation extends Model
{
    protected $fillable = [
        'author_id', 'target_id', 'body', 'rating', 'is_hidden',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_hidden' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
