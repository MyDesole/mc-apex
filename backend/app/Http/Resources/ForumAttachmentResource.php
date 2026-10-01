<?php

namespace App\Http\Resources;

use App\Models\ForumAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Вложение форума или чата.
 *
 * @property ForumAttachment $resource
 */
class ForumAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $file = $this->resource;

        return [
            'id' => $file->id,
            'name' => $file->original_name,
            'url' => $file->url,
            'size' => $file->human_size,
            'is_image' => (bool) $file->is_image,
        ];
    }
}
