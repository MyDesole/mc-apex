<?php

namespace App\Domains\Forum\Policies;

use App\Domains\Forum\Models\ForumReply;
use App\Domains\Users\Models\User;

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
