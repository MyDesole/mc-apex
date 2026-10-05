<?php

namespace App\Domains\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Вид бриджа: спидбридж, годбридж, телли и т.д.
 *
 * У вида есть подвиды — уточнения, которые показываются пиллами.
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

    /** Подвиды: только активные, по порядку. */
    public function variants(): HasMany
    {
        return $this->hasMany(BridgeTechniqueVariant::class, 'technique_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Все подвиды, включая выключенные — для панели куратора. */
    public function allVariants(): HasMany
    {
        return $this->hasMany(BridgeTechniqueVariant::class, 'technique_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
