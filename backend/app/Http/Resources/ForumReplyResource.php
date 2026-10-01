<?php

namespace App\Http\Resources;

use App\Models\ForumLike;
use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ в форуме. Дерево собирается отдельно (ForumReplyTree),
 * потому что вложенность не выражается одним ресурсом.
 *
 * @property ForumReply $resource
 */
class ForumReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reply = $this->resource;
        $me = $request->user();
        $deleted = $reply->isDeleted();

        return [
            'id' => $reply->id,
            'topic_id' => $reply->topic_id,
            'parent_id' => $reply->parent_id,
            'depth' => 0,
            'children' => [],
            'body' => $deleted ? null : $reply->body,
            'is_deleted' => $deleted,
            'author' => $deleted || ! $reply->author
                ? null
                : new ForumAuthorResource($reply->author),
            'likes_count' => (int) $reply->likes_count,
            'liked' => $this->liked($me, 'reply', $reply->id),
            'attachments' => $deleted
                ? []
                : ForumAttachmentResource::collection(
                    $reply->relationLoaded('attachments') ? $reply->attachments : []
                ),
            'edited_at' => $reply->edited_at?->toIso8601String(),
            'created_at' => $reply->created_at?->toIso8601String(),
            'can_edit' => $this->canModify($reply->author_id, $me),
            'can_delete' => $this->canModify($reply->author_id, $me),
        ];
    }

    private function liked(?User $me, string $type, int $id): bool
    {
        if (! $me) {
            return false;
        }

        return ForumLike::where('user_id', $me->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $id)
            ->exists();
    }

    private function canModify(int $authorId, ?User $me): bool
    {
        if (! $me) {
            return false;
        }

        return $authorId === $me->id || $me->isModerator();
    }
}
