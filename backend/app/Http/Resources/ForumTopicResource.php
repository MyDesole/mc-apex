<?php

namespace App\Http\Resources;

use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Тема форума.
 *
 * Заменил ForumPresenter: формат один и тот же, но теперь это нативный
 * ресурс — коллекции, обёртки и условные поля даёт Laravel.
 *
 * @property ForumTopic $resource
 */
class ForumTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $topic = $this->resource;
        $me = $request->user();

        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'body' => $topic->body,
            'category_id' => $topic->category_id,
            'category' => $topic->category ? [
                'id' => $topic->category->id,
                'slug' => $topic->category->slug,
                'name' => $topic->category->name,
                'color' => $topic->category->color,
                'icon' => $topic->category->icon,
            ] : null,
            'author' => $topic->author ? new ForumAuthorResource($topic->author) : null,
            'is_pinned' => (bool) $topic->is_pinned,
            'is_locked' => (bool) $topic->is_locked,
            'views' => (int) $topic->views,
            'replies_count' => (int) $topic->replies_count,
            'likes_count' => (int) $topic->likes_count,
            'liked' => $this->liked($me, 'topic', $topic->id),
            'attachments' => ForumAttachmentResource::collection(
                $topic->relationLoaded('attachments') ? $topic->attachments : []
            ),
            'created_at' => $topic->created_at?->toIso8601String(),
            'last_reply_at' => ($topic->last_reply_at ?? $topic->created_at)?->toIso8601String(),
            'last_reply_user' => $topic->lastReplyUser?->username,
            'can_edit' => $this->canModify($topic->author_id, $me),
            'can_delete' => $this->canModify($topic->author_id, $me),
        ];
    }

    private function liked(?User $me, string $type, int $id): bool
    {
        if (! $me) {
            return false;
        }

        return \App\Models\ForumLike::where('user_id', $me->id)
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
