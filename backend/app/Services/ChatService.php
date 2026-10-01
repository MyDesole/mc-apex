<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRead;
use App\Models\User;
use App\Http\Resources\MessageResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Вся логика чата: диалоги, сообщения, вложения, прочтения.
 *
 * Контроллер здесь только принимает запрос и отдаёт результат — транзакции,
 * подсчёты и правила живут в сервисе.
 */
class ChatService
{
    /** Сколько сообщений отдаём за одну страницу истории. */
    public const PAGE_SIZE = 40;

    /** Сколько диалогов показываем в списке. */
    public const CONVERSATION_LIMIT = 50;

    private const MESSAGE_RELATIONS = [
        'user:id,username,avatar,tier,is_verified',
        'reads:id,message_id,user_id',
        'replyTo.user:id,username,avatar',
        'replyTo.attachments',
        'forwardedFrom',
        'attachments',
    ];

    private const CONVERSATION_RELATIONS = [
        'users:id,username,avatar,tier,is_verified',
        'clan:id,name,tag,banner_color,avatar',
        'author:id,username,avatar,tier,is_verified',
    ];

    /* ----------------------------- Диалоги ----------------------------- */

    /**
     * Список диалогов пользователя с непрочитанными.
     *
     * Непрочитанные считаются одним запросом на все диалоги — раньше это
     * было N+1: по запросу на каждый диалог.
     */
    public function listConversations(User $me): Collection
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id))
            ->with([...self::CONVERSATION_RELATIONS, 'lastMessage.user:id,username,avatar'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(self::CONVERSATION_LIMIT)
            ->get();

        $unreadByConversation = Message::query()
            ->selectRaw('conversation_id, count(*) as total')
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
            ->groupBy('conversation_id')
            ->pluck('total', 'conversation_id');

        $participantCounts = ConversationParticipant::query()
            ->selectRaw('conversation_id, count(*) as total')
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('user_id', '!=', $me->id)
            ->groupBy('conversation_id')
            ->pluck('total', 'conversation_id');

        $conversations->each(function (Conversation $conversation) use ($unreadByConversation, $participantCounts) {
            $conversation->unread_count = (int) ($unreadByConversation[$conversation->id] ?? 0);
            $conversation->other_participants_count = (int) ($participantCounts[$conversation->id] ?? 0);
        });

        return $conversations;
    }

    /**
     * История сообщений с курсорной пагинацией.
     *
     * @return array{messages: array, has_more: bool, oldest_id: ?int, newest_id: ?int}
     */
    public function history(
        Conversation $conversation,
        User $me,
        ?int $beforeId = null,
        ?int $afterId = null,
        int $limit = self::PAGE_SIZE,
        bool $markRead = true,
    ): array {
        if ($markRead) {
            $this->markRead($conversation, $me);
        }

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(self::MESSAGE_RELATIONS);

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        // Берём «хвост» — свежие сообщения, затем разворачиваем в хронологию
        $messages = $query->orderByDesc('id')->limit($limit + 1)->get();

        $hasMore = $messages->count() > $limit;

        if ($hasMore) {
            $messages = $messages->slice(0, $limit);
        }

        $messages = $messages->sortBy('id')->values();

        return [
            'messages' => $this->presentMessages($messages, $me),
            'has_more' => $hasMore,
            'oldest_id' => $messages->first()->id ?? null,
            'newest_id' => $messages->last()->id ?? null,
        ];
    }

    /**
     * Найти или создать личный диалог.
     */
    public function startDirect(User $me, int $otherUserId): Conversation
    {
        if ($otherUserId === $me->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Нельзя начать диалог с самим собой.'],
            ]);
        }

        return DB::transaction(function () use ($me, $otherUserId) {
            $conversation = Conversation::findOrCreateDirect($me->id, $otherUserId);

            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id' => $me->id,
            ]);

            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id' => $otherUserId,
            ]);

            return $conversation;
        });
    }

    /**
     * Обращение к клану: участники — автор и руководство клана.
     */
    public function startClan(User $me, int $clanId): Conversation
    {
        $clan = Clan::findOrFail($clanId);

        return DB::transaction(function () use ($me, $clan) {
            $conversation = Conversation::firstOrCreate(
                [
                    'type' => 'clan_message',
                    'clan_id' => $clan->id,
                    'author_id' => $me->id,
                ],
                ['last_message_at' => now()]
            );

            $staffIds = ClanMember::where('clan_id', $clan->id)
                ->whereIn('role', ['leader', 'officer'])
                ->pluck('user_id')
                ->all();

            $participantIds = array_unique(array_merge([$me->id], $staffIds));

            foreach ($participantIds as $userId) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }

            return $conversation;
        });
    }

    /* ----------------------------- Сообщения ----------------------------- */

    /**
     * Отправить сообщение (текст и/или вложения).
     */
    public function send(
        Conversation $conversation,
        User $me,
        ?string $body,
        ?int $replyToId = null,
        ?int $forwardedFromUserId = null,
        array $attachmentIds = [],
    ): Message {
        $body = trim((string) $body);

        if ($body === '' && ! $attachmentIds) {
            throw ValidationException::withMessages([
                'body' => ['Сообщение не может быть пустым.'],
            ]);
        }

        if ($replyToId) {
            $this->assertReplyTargetInConversation($conversation, $replyToId);
        }

        $message = DB::transaction(function () use (
            $conversation, $me, $body, $replyToId, $forwardedFromUserId, $attachmentIds
        ) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $me->id,
                'reply_to_id' => $replyToId,
                'forwarded_from_user_id' => $forwardedFromUserId,
                'body' => $body,
            ]);

            $this->attachFiles($message, $attachmentIds);

            $conversation->update(['last_message_at' => now()]);

            MessageRead::firstOrCreate(
                ['message_id' => $message->id, 'user_id' => $me->id],
                ['read_at' => now()]
            );

            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $me->id)
                ->update(['last_read_at' => now()]);

            return $message;
        });

        return $message->fresh()->load(self::MESSAGE_RELATIONS);
    }

    /**
     * Переслать сообщение в другой диалог вместе с вложениями.
     */
    public function forward(Message $message, Conversation $target, User $me): Message
    {
        if ($target->id === $message->conversation_id) {
            throw ValidationException::withMessages([
                'conversation_id' => ['Нельзя переслать сообщение в тот же диалог.'],
            ]);
        }

        $forwarded = DB::transaction(function () use ($message, $target, $me) {
            $copy = Message::create([
                'conversation_id' => $target->id,
                'user_id' => $me->id,
                'forwarded_from_user_id' => $message->user_id,
                'body' => $message->body,
            ]);

            foreach ($message->attachments as $attachment) {
                MessageAttachment::create([
                    'message_id' => $copy->id,
                    'user_id' => $me->id,
                    'original_name' => $attachment->original_name,
                    'path' => $attachment->path,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'is_image' => $attachment->is_image,
                ]);
            }

            $target->update(['last_message_at' => now()]);

            MessageRead::firstOrCreate(
                ['message_id' => $copy->id, 'user_id' => $me->id],
                ['read_at' => now()]
            );

            ConversationParticipant::where('conversation_id', $target->id)
                ->where('user_id', $me->id)
                ->update(['last_read_at' => now()]);

            return $copy;
        });

        return $forwarded->fresh()->load(self::MESSAGE_RELATIONS);
    }

    /**
     * Изменить текст своего сообщения (в течение суток).
     */
    public function updateMessage(Message $message, string $body): Message
    {
        if ($message->created_at && $message->created_at->diffInHours(now()) > 24) {
            throw ValidationException::withMessages([
                'body' => ['Редактировать сообщение можно в течение 24 часов.'],
            ]);
        }

        $message->update([
            'body' => trim($body),
            'edited_at' => now(),
        ]);

        return $message->fresh()->load(self::MESSAGE_RELATIONS);
    }

    public function deleteMessage(Message $message): void
    {
        $conversationId = $message->conversation_id;

        $message->delete();

        Conversation::whereKey($conversationId)->update(['last_message_at' => now()]);
    }

    /* ----------------------------- Вложения ----------------------------- */

    /**
     * Загрузить файл «про запас»: id потом передаётся при отправке сообщения.
     */
    public function uploadAttachment(User $me, UploadedFile $file): MessageAttachment
    {
        $path = $file->store('chat/' . now()->format('Y/m'), 'public');

        $mime = $file->getClientMimeType() ?: $file->getMimeType();

        return MessageAttachment::create([
            'message_id' => null,
            'user_id' => $me->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'is_image' => str_starts_with((string) $mime, 'image/'),
        ]);
    }

    public function attachmentPath(MessageAttachment $attachment): string
    {
        abort_unless(Storage::disk('public')->exists($attachment->path), 404);

        return $attachment->path;
    }

    /* ----------------------------- Прочтения ----------------------------- */

    /**
     * Пометить весь диалог прочитанным.
     */
    public function markRead(Conversation $conversation, User $me): void
    {
        $unreadIds = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
            ->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            $now = now();

            MessageRead::insertOrIgnore(
                $unreadIds->map(fn ($id) => [
                    'message_id' => $id,
                    'user_id' => $me->id,
                    'read_at' => $now,
                ])->all()
            );
        }

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $me->id)
            ->update(['last_read_at' => now()]);
    }

    public function markMessageRead(Message $message, User $me): void
    {
        if ($message->user_id === $me->id) {
            return;
        }

        MessageRead::firstOrCreate(
            ['message_id' => $message->id, 'user_id' => $me->id],
            ['read_at' => now()]
        );
    }

    /**
     * Общее число непрочитанных сообщений пользователя.
     */
    public function unreadCount(User $me): int
    {
        $conversationIds = ConversationParticipant::where('user_id', $me->id)
            ->pluck('conversation_id');

        return Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
            ->count();
    }

    /* ----------------------------- Поиск ----------------------------- */

    /**
     * Пустой запрос — последние собеседники (друзья),
     * 2+ символа — поиск по игрокам и кланам.
     *
     * @return array{users: Collection, clans: Collection}
     */
    public function search(User $me, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['users' => $this->recentPeers($me), 'clans' => collect()];
        }

        if (mb_strlen($query) < 2) {
            return ['users' => collect(), 'clans' => collect()];
        }

        $like = "%{$query}%";

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->where('username', 'like', $like)
            ->select('id', 'username', 'avatar', 'tier', 'is_verified')
            ->orderByDesc('tier_score')
            ->limit(10)
            ->get();

        $clans = Clan::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('tag', 'like', $like))
            ->select('id', 'name', 'tag', 'banner_color', 'avatar')
            ->limit(10)
            ->get();

        return ['users' => $users, 'clans' => $clans];
    }

    /**
     * Друзья пользователя — стартовый список для пересылки.
     */
    private function recentPeers(User $me): Collection
    {
        $friendIds = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $me->id)->orWhere('friend_id', $me->id))
            ->get()
            ->map(fn ($f) => $f->user_id === $me->id ? $f->friend_id : $f->user_id)
            ->unique()
            ->take(10)
            ->values();

        return User::query()
            ->whereIn('id', $friendIds)
            ->select('id', 'username', 'avatar', 'tier', 'is_verified')
            ->orderByDesc('tier_score')
            ->get();
    }

    /* ----------------------------- Внутреннее ----------------------------- */

    /**
     * Привязать ранее загруженные файлы к сообщению.
     * Чужие и уже привязанные вложения игнорируются.
     */
    private function attachFiles(Message $message, array $attachmentIds): void
    {
        if (! $attachmentIds) {
            return;
        }

        MessageAttachment::whereIn('id', $attachmentIds)
            ->whereNull('message_id')
            ->update(['message_id' => $message->id]);
    }

    private function assertReplyTargetInConversation(Conversation $conversation, int $replyToId): void
    {
        $original = Message::find($replyToId);

        if (! $original || $original->conversation_id !== $conversation->id) {
            throw ValidationException::withMessages([
                'reply_to_id' => ['Нельзя ответить на сообщение из другого диалога.'],
            ]);
        }
    }

    /**
     * История в формате ресурса.
     *
     * Ресурс берёт пользователя из request(), поэтому выставляем резолвер:
     * сервис может вызываться и вне HTTP-запроса.
     *
     * @param  iterable<Message>  $messages
     */
    private function presentMessages(iterable $messages, ?User $me): array
    {
        $request = request();

        $previous = $request->getUserResolver();

        $request->setUserResolver(fn () => $me);

        $result = [];

        foreach ($messages as $message) {
            $result[] = (new MessageResource($message))->resolve();
        }

        $request->setUserResolver($previous);

        return $result;
    }

    /**
     * Данные диалога для ответа API.
     */
    public function conversationPayload(Conversation $conversation): array
    {
        return $conversation->load(self::CONVERSATION_RELATIONS)->toArray();
    }
}
