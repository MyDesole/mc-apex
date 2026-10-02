<?php

namespace App\Domains\Chat\Policies;

use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Users\Models\User;

class MessageAttachmentPolicy
{
    /**
     * Участник диалога видит вложения его сообщений.
     * Ещё не отправленный файл доступен только тому, кто его загрузил.
     */
    public function download(User $user, MessageAttachment $attachment): bool
    {
        $message = $attachment->message;

        if (! $message) {
            return $attachment->user_id === $user->id;
        }

        return $message->conversation()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
