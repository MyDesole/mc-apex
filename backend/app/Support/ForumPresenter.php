<?php

namespace App\Support;

use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * Формат категории форума для списка разделов.
 *
 * Тема и ответ переехали в ForumTopicResource и ForumReplyResource;
 * здесь остаётся только категория: у неё свои счётчики и политика
 * публикации, которые считаются по запросу, а не берутся из модели.
 */
class ForumPresenter
{
    public static function category(ForumCategory $category, ?User $me, ?ForumTopic $lastTopic): array
    {
        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'icon' => $category->icon,
            'color' => $category->color,
            'post_policy' => $category->post_policy,
            'policy_label' => $category->policyLabel(),
            'can_post' => $category->allowsPosting($me),
            'topics_count' => ForumTopic::visible()->where('category_id', $category->id)->count(),
            'replies_count' => ForumReply::visible()
                ->whereHas('topic', fn ($q) => $q->where('category_id', $category->id))
                ->count(),
            'last_topic' => $lastTopic ? [
                'id' => $lastTopic->id,
                'title' => $lastTopic->title,
                'author' => $lastTopic->author?->username,
                'last_reply_at' => ($lastTopic->last_reply_at ?? $lastTopic->created_at)?->toIso8601String(),
                'last_reply_user' => $lastTopic->lastReplyUser?->username ?? $lastTopic->author?->username,
            ] : null,
        ];
    }
}
