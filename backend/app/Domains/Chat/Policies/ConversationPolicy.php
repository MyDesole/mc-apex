<?php

namespace App\Domains\Chat\Policies;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Users\Models\User;

class ConversationPolicy
{
    /**
     * Видеть диалог и его сообщения может только участник.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    /**
     * Писать в диалог может только участник.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    /**
     * Пометка прочитанным — тоже только для участника.
     */
    public function markRead(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    private function isParticipant(User $user, Conversation $conversation): bool
    {
        return ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
