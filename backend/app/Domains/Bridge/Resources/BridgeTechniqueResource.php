<?php

namespace App\Domains\Bridge\Resources;

use App\Domains\Bridge\Models\BridgeTechnique;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Вид бриджа из каталога.
 *
 * @property BridgeTechnique $resource
 */
class BridgeTechniqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'description' => $this->resource->description,
        ];
    }
}
