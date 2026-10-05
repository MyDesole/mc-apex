<?php

namespace App\Domains\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Вид бриджа: спидбридж, годбридж, телли и т.д.
 */
class BridgeTechnique extends Model
{
    protected $fillable = [
        'key', 'label', 'description', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(UserBridgeTechnique::class, 'technique_id');
    }
}
