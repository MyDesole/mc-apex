<?php

namespace App\Domains\Chat\Policies;

use App\Domains\Chat\Models\Message;
use App\Domains\Users\Models\User;

class MessagePolicy
{
    /**
     * Читать сообщение может участник его диалога.
     */
    public function view(User $user, Message $message): bool
    {
        return $this->inConversation($user, $message);
    }

    /**
     * Пересылать можно только то, что видишь сам.
     */
    public function forward(User $user, Message $message): bool
    {
        return $this->inConversation($user, $message);
    }

    /**
     * Править — только своё сообщение.
     */
    public function update(User $user, Message $message): bool
    {
        return $message->user_id === $user->id;
    }

    /**
     * Удалять — своё сообщение либо модератор.
     */
    public function delete(User $user, Message $message): bool
    {
        return $message->user_id === $user->id || $user->isModerator();
    }

    private function inConversation(User $user, Message $message): bool
    {
        return $message->conversation()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
