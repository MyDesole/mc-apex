<?php

namespace App\Domains\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Подвид бриджа: уточнение внутри вида, показывается пиллой.
 */
class BridgeTechniqueVariant extends Model
{
    protected $fillable = [
        'technique_id', 'key', 'label', 'is_special', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_special' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function technique(): BelongsTo
    {
        return $this->belongsTo(BridgeTechnique::class, 'technique_id');
    }
}
