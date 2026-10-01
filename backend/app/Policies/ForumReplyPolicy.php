<?php

namespace App\Policies;

use App\Models\ForumReply;
use App\Models\User;

/**
 * Ответ в форуме: автор правит своё, модератор — любое.
 */
class ForumReplyPolicy
{
    public function update(User $user, ForumReply $reply): bool
    {
        return $reply->author_id === $user->id || $user->isModerator();
    }

    public function delete(User $user, ForumReply $reply): bool
    {
        return $reply->author_id === $user->id || $user->isModerator();
    }
}
