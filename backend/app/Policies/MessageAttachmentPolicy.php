<?php

namespace App\Policies;

use App\Models\MessageAttachment;
use App\Models\User;

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
