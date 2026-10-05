<?php

namespace App\Domains\Bridge\Resources;

use App\Domains\Bridge\Models\BridgeTechnique;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Вид бриджа из каталога вместе с подвидами.
 *
 * @property BridgeTechnique $resource
 */
class BridgeTechniqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $technique = $this->resource;

        return [
            'id' => $technique->id,
            'key' => $technique->key,
            'label' => $technique->label,
            'description' => $technique->description,
            'is_active' => (bool) $technique->is_active,
            'sort_order' => (int) $technique->sort_order,

            // Пиллы под названием вида
            'variants' => $technique->relationLoaded('variants')
                ? $technique->variants
                    ->map(fn ($variant) => [
                        'id' => $variant->id,
                        'key' => $variant->key,
                        'label' => $variant->label,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }
}
