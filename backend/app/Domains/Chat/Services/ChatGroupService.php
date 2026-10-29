<?php

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Chat\Models\Message;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Групповые беседы: создание, состав, название.
 *
 * Звать в беседу можно только своих друзей — так беседы не становятся
 * рассылкой незнакомым. Переименовывает и исключает создатель, выйти
 * может любой. Каждое изменение оставляет в ленте служебную отметку.
 */
class ChatGroupService
{
    /** Сколько людей помещается в беседу вместе с создателем. */
    public const MAX_MEMBERS = 20;

    public function __construct(private readonly ChatService $chat)
    {
    }

    /**
     * @param  array<int>  $userIds  кого позвать, кроме себя
     * @return array{0: Conversation, 1: Message}
     */
    public function create(User $me, string $title, array $userIds): array
    {
        $title = $this->cleanTitle($title);
        $userIds = $this->friendIds($me, $userIds);

        if (! $userIds) {
            throw ValidationException::withMessages([
                'user_ids' => ['Позови в беседу хотя бы одного друга.'],
            ]);
        }

        if (count($userIds) + 1 > self::MAX_MEMBERS) {
            throw ValidationException::withMessages([
                'user_ids' => ['В беседе может быть не больше ' . self::MAX_MEMBERS . ' человек.'],
            ]);
        }

        return DB::transaction(function () use ($me, $title, $userIds) {
            $conversation = Conversation::create([
                'type' => 'group',
                'title' => $title,
                'owner_id' => $me->id,
                'last_message_at' => now(),
            ]);

            foreach ([$me->id, ...$userIds] as $userId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }

            $message = $this->system($conversation, $me, 'created', $userIds, "{$me->username} создаёт беседу «{$title}»");

            return [$conversation, $message];
        });
    }

    /**
     * Позвать друзей в беседу. Звать может любой участник.
     *
     * @param  array<int>  $userIds
     */
    public function addMembers(Conversation $conversation, User $me, array $userIds): Message
    {
        $this->assertGroup($conversation);

        $present = $conversation->participants()->pluck('user_id')->all();
        $userIds = array_values(array_diff($this->friendIds($me, $userIds), $present));

        if (! $userIds) {
            throw ValidationException::withMessages([
                'user_ids' => ['Эти друзья уже в беседе.'],
            ]);
        }

        if (count($present) + count($userIds) > self::MAX_MEMBERS) {
            throw ValidationException::withMessages([
                'user_ids' => ['В беседе может быть не больше ' . self::MAX_MEMBERS . ' человек.'],
            ]);
        }

        return DB::transaction(function () use ($conversation, $me, $userIds) {
            foreach ($userIds as $userId) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }

            $names = User::whereIn('id', $userIds)->pluck('username')->implode(', ');

            return $this->system($conversation, $me, 'added', $userIds, "{$me->username} зовёт в беседу: {$names}");
        });
    }

    /**
     * Исключить участника или выйти самому.
     *
     * Создатель ушёл — беседа переходит к тому, кто в ней дольше всех.
     * Ушёл последний — беседа удаляется (null).
     */
    public function removeMember(Conversation $conversation, User $me, User $target): ?Message
    {
        $this->assertGroup($conversation);

        $leaving = $target->id === $me->id;

        if (! $leaving && $conversation->owner_id !== $me->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Исключать может только создатель беседы.'],
            ]);
        }

        $participant = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $target->id)
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages(['user_id' => ['Этого игрока нет в беседе.']]);
        }

        return DB::transaction(function () use ($conversation, $me, $target, $leaving, $participant) {
            $participant->delete();

            $next = ConversationParticipant::where('conversation_id', $conversation->id)
                ->orderBy('id')
                ->value('user_id');

            if (! $next) {
                $conversation->delete();

                return null;
            }

            if ($conversation->owner_id === $target->id) {
                $conversation->update(['owner_id' => $next]);
            }

            $text = $leaving
                ? "{$me->username} выходит из беседы"
                : "{$me->username} исключает {$target->username}";

            return $this->system($conversation, $me, $leaving ? 'left' : 'removed', [$target->id], $text);
        });
    }

    public function rename(Conversation $conversation, User $me, string $title): Message
    {
        $this->assertGroup($conversation);

        if ($conversation->owner_id !== $me->id) {
            throw ValidationException::withMessages([
                'title' => ['Переименовать беседу может только её создатель.'],
            ]);
        }

        $title = $this->cleanTitle($title);

        return DB::transaction(function () use ($conversation, $me, $title) {
            $conversation->update(['title' => $title]);

            return $this->system($conversation, $me, 'renamed', [], "{$me->username} называет беседу «{$title}»");
        });
    }

    /* ----------------------------- Внутреннее ----------------------------- */

    private function system(Conversation $conversation, User $me, string $action, array $targetIds, string $text): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'kind' => Message::KIND_SYSTEM,
            'body' => $text,
            'meta' => ['action' => $action, 'target_ids' => array_values($targetIds)],
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Своя отметка у автора прочитана сразу
        \App\Domains\Chat\Models\MessageRead::firstOrCreate(
            ['message_id' => $message->id, 'user_id' => $me->id],
            ['read_at' => now()]
        );

        return $this->chat->reload($message, $me);
    }

    /**
     * Из списка оставляем только друзей: незнакомых в беседу не зовут.
     *
     * @return array<int>
     */
    private function friendIds(User $me, array $userIds): array
    {
        $userIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $me->id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $friends = \App\Domains\Friends\Models\Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('user_id', $me->id)->whereIn('friend_id', $userIds))
                ->orWhere(fn ($q) => $q->where('friend_id', $me->id)->whereIn('user_id', $userIds)))
            ->get(['user_id', 'friend_id'])
            ->map(fn ($f) => (int) ((int) $f->user_id === $me->id ? $f->friend_id : $f->user_id))
            ->unique()
            ->all();

        $strangers = $userIds->diff($friends);

        if ($strangers->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['В беседу можно звать только друзей.'],
            ]);
        }

        return $userIds->all();
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));

        if (mb_strlen($title) < 2 || mb_strlen($title) > 48) {
            throw ValidationException::withMessages([
                'title' => ['Название беседы — от 2 до 48 символов.'],
            ]);
        }

        return $title;
    }

    private function assertGroup(Conversation $conversation): void
    {
        if (! $conversation->isGroup()) {
            throw ValidationException::withMessages([
                'conversation' => ['Это не групповая беседа.'],
            ]);
        }
    }
}
