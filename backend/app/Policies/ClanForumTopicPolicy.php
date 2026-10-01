<?php

namespace App\Policies;

use App\Models\ClanForumTopic;
use App\Models\User;

/**
 * Темы кланового форума: удаляет автор, лидер или офицер.
 */
class ClanForumTopicPolicy
{
    public function delete(User $user, ClanForumTopic $topic): bool
    {
        if ($topic->author_id === $user->id) {
            return true;
        }

        return in_array($user->clanMember?->role, ['leader', 'officer'], true);
    }
}
