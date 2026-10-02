<?php

namespace App\Domains\Forum\Policies;

use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Users\Models\User;

/**
 * Тема форума: автор распоряжается своей, модератор — любой.
 *
 * Срок давности правки проверяется в сервисе: это бизнес-правило,
 * которое даёт 422, а не отказ в доступе.
 */
class ForumTopicPolicy
{
    public function update(User $user, ForumTopic $topic): bool
    {
        return $topic->author_id === $user->id || $user->isModerator();
    }

    public function delete(User $user, ForumTopic $topic): bool
    {
        return $topic->author_id === $user->id || $user->isModerator();
    }

    /** Создавать темы может любой авторизованный: раздел решает сам. */
    public function create(User $user): bool
    {
        return true;
    }
}
