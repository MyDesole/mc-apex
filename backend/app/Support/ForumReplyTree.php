<?php

namespace App\Support;

use App\Http\Resources\ForumAttachmentResource;
use App\Http\Resources\ForumReplyResource;
use App\Models\ForumAttachment;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * Сборка дерева ответов форума.
 *
 * Вложенность нельзя выразить одним JsonResource, поэтому структуру
 * строит этот класс, а формат каждого узла задаёт ForumReplyResource.
 */
class ForumReplyTree
{
    /**
     * @param  iterable<ForumReply>  $replies
     * @return array<int, array>
     */
    public static function build(iterable $replies, ?User $me = null): array
    {
        // Формат узла задаёт ресурс, а он берёт пользователя из request().
        // Выставляем резолвер явно: сервис может вызываться и вне HTTP-запроса.
        $request = request();

        $previous = $request->getUserResolver();

        $request->setUserResolver(fn () => $me);

        $presented = [];

        foreach ($replies as $reply) {
            $presented[$reply->id] = (new ForumReplyResource($reply))->resolve();
        }

        $request->setUserResolver($previous);

        $children = [];

        foreach ($replies as $reply) {
            $parentId = $reply->parent_id;

            // Родителя нет среди ответов темы — считаем корневым,
            // чтобы ветка не потерялась
            if (! $parentId || ! isset($presented[$parentId])) {
                $parentId = 0;
            }

            $children[$parentId][] = $reply->id;
        }

        $build = function (int $parentId, int $depth = 0) use (&$build, $children, $presented) {
            $result = [];

            foreach ($children[$parentId] ?? [] as $replyId) {
                $node = $presented[$replyId];
                $node['depth'] = $depth;
                $node['children'] = $build($replyId, $depth + 1);
                $result[] = $node;
            }

            return $result;
        };

        return $build(0);
    }

    /**
     * Плоский список вложений: нужен там, где ответ отдаётся отдельно.
     */
    public static function attachments(ForumTopic|ForumReply $model): array
    {
        if (! $model->relationLoaded('attachments')) {
            return [];
        }

        return ForumAttachmentResource::collection($model->attachments)->resolve();
    }
}
