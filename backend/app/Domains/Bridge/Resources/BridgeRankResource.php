<?php

namespace App\Domains\Bridge\Resources;

use App\Domains\Bridge\Models\BridgeRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Звание бриджера.
 *
 * @property BridgeRank $resource
 */
class BridgeRankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'color' => $this->resource->color,
        ];
    }
}
