<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сообщение чата.
 *
 * Заменил MessagePresenter: тот собирал массив вручную в трёх местах
 * (история, отправка, пересылка) и успел разойтись по составу полей.
 *
 * @property Message $resource
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $me = $request->user();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body ?? '',
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
            'user' => $this->userPayload(),
            'reads' => $this->reads(),
            'reply_to' => $this->replyTo(),
            'forwarded_from' => $message->forwardedFrom ? [
                'id' => $message->forwardedFrom->id,
                'username' => $message->forwardedFrom->username,
            ] : null,
            'attachments' => $this->attachments(),
            'is_mine' => $me ? $message->user_id === $me->id : false,
        ];
    }

    private function userPayload(): ?array
    {
        $user = $this->resource->user;

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'tier' => $user->tier,
            'is_verified' => (bool) $user->is_verified,
        ];
    }

    private function reads(): array
    {
        $message = $this->resource;

        if (! $message->relationLoaded('reads')) {
            return [];
        }

        return $message->reads->map(fn ($read) => ['user_id' => $read->user_id])
            ->values()
            ->all();
    }

    private function replyTo(): ?array
    {
        $message = $this->resource;

        if (! $message->relationLoaded('replyTo') || ! $message->replyTo) {
            return null;
        }

        $original = $message->replyTo;

        return [
            'id' => $original->id,
            'body' => $original->body ?? '',
            'user' => $original->user ? [
                'id' => $original->user->id,
                'username' => $original->user->username,
            ] : null,
            'attachments' => $original->relationLoaded('attachments')
                ? $original->attachments->map(fn (MessageAttachment $a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'is_image' => (bool) $a->is_image,
                ])->values()->all()
                : [],
        ];
    }

    private function attachments(): array
    {
        $message = $this->resource;

        if (! $message->relationLoaded('attachments')) {
            return [];
        }

        return $message->attachments->map(fn (MessageAttachment $a) => [
            'id' => $a->id,
            'name' => $a->original_name,
            'url' => $a->url,
            'size' => $a->human_size,
            'is_image' => (bool) $a->is_image,
        ])->values()->all();
    }
}
