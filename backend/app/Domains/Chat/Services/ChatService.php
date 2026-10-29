<?php

namespace App\Domains\Chat\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Friends\Models\Friendship;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Chat\Models\MessageRead;
use App\Domains\Chat\Models\MessageReaction;
use App\Domains\Users\Models\User;
use App\Domains\Chat\Resources\MessageResource;
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

    /** Самое длинное голосовое, секунд. */
    public const VOICE_MAX_SECONDS = 120;

    private const MESSAGE_RELATIONS = [
        'user:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified',
        'reads:id,message_id,user_id',
        'replyTo.user:id,username,avatar',
        'replyTo.attachments',
        'forwardedFrom',
        'attachments',
        'reactions:id,message_id,user_id,emoji',
    ];

    private const CONVERSATION_RELATIONS = [
        'users:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified',
        'clan:id,name,tag,banner_color,avatar',
        'author:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified',
        'owner:id,username',
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
            /*
             * Пустой личный диалог не показываем: он создаётся, как только
             * кто-то открыл чат с игроком, и у собеседника висел «Новый
             * диалог» без единого сообщения.
             */
            ->where(fn ($q) => $q->where('type', '!=', 'direct')->orWhereHas('messages'))
            // Вложения последнего — чтобы превью писало «Фото» или «Голосовое», а не пустоту
            ->with([...self::CONVERSATION_RELATIONS, 'lastMessage.user:id,username,avatar', 'lastMessage.attachments'])
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

        $this->markParticipantsOnline($conversations);

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
        /*
         * Первое непрочитанное — до отметки прочтения, иначе его уже не
         * найти. По нему страница рисует разделитель «Новые сообщения».
         */
        $firstUnreadId = (! $beforeId && ! $afterId)
            ? $this->unreadQuery($me)->where('conversation_id', $conversation->id)->min('id')
            : null;

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
            /*
             * Догрузка новых — с начала по порядку. Раньше и здесь брался
             * «хвост», и при большом разрыве середина терялась навсегда:
             * следующий запрос шёл уже после самого свежего id.
             */
            $messages = $query->where('id', '>', $afterId)->orderBy('id')->limit($limit + 1)->get();
            $hasMore = $messages->count() > $limit;
            $messages = $messages->slice(0, $limit)->values();
        } else {
            // Берём «хвост» — свежие сообщения, затем разворачиваем в хронологию
            $messages = $query->orderByDesc('id')->limit($limit + 1)->get();
            $hasMore = $messages->count() > $limit;
            $messages = $messages->slice(0, $limit)->sortBy('id')->values();
        }

        return [
            'messages' => $this->presentMessages($messages, $me),
            'has_more' => $hasMore,
            'oldest_id' => $messages->first()->id ?? null,
            'newest_id' => $messages->last()->id ?? null,
            'first_unread_id' => $firstUnreadId ? (int) $firstUnreadId : null,
        ];
    }

    /** Чужие сообщения, которые я ещё не прочитал. */
    private function unreadQuery(User $me)
    {
        return Message::query()
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id));
    }

    /**
     * Помечает собеседников в списке диалогов онлайн или офлайн.
     *
     * Значение кладётся прямо в участников диалога: тогда точке онлайна
     * не нужен отдельный запрос при отрисовке списка.
     *
     * @param  \Illuminate\Support\Collection<int, Conversation>  $conversations
     */
    private function markParticipantsOnline($conversations): void
    {
        $online = array_flip(app(PresenceService::class)->onlineUserIds());

        foreach ($conversations as $conversation) {
            foreach ($conversation->users ?? [] as $user) {
                $user->is_online = isset($online[(int) $user->id]);
            }
        }
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
        array $attachmentIds = [],
    ): Message {
        $body = trim((string) $body);

        /*
         * Вложения — только свои и ещё ни к чему не привязанные. Проверяем
         * до записи: иначе сообщение из одних чужих id уходило пустым.
         */
        $attachmentIds = $attachmentIds
            ? MessageAttachment::whereIn('id', $attachmentIds)
                ->where('user_id', $me->id)
                ->whereNull('message_id')
                ->pluck('id')
                ->all()
            : [];

        if ($body === '' && ! $attachmentIds) {
            throw ValidationException::withMessages([
                'body' => ['Сообщение не может быть пустым.'],
            ]);
        }

        if ($replyToId) {
            $this->assertReplyTargetInConversation($conversation, $replyToId);
        }

        /*
         * Стикер — только из своих наборов. Раньше проверку можно было
         * обойти, передав forwarded_from_user_id: платные наборы уходили
         * бесплатно. Пересылка теперь идёт только через forward().
         */
        app(\App\Domains\Shop\Services\StickerService::class)->guard($me, $body);

        $message = DB::transaction(function () use ($conversation, $me, $body, $replyToId, $attachmentIds) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $me->id,
                'reply_to_id' => $replyToId,
                'body' => $body,
            ]);

            $this->attachFiles($message, $attachmentIds);

            $this->touchAfterWrite($conversation, $message, $me);

            return $message;
        });

        return $this->reload($message, $me);
    }

    /**
     * После нового сообщения: диалог поднимается в списке, а у автора
     * оно сразу прочитано.
     */
    private function touchAfterWrite(Conversation $conversation, Message $message, User $me): void
    {
        $conversation->update(['last_message_at' => now()]);

        MessageRead::firstOrCreate(
            ['message_id' => $message->id, 'user_id' => $me->id],
            ['read_at' => now()]
        );

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $me->id)
            ->update(['last_read_at' => now()]);
    }

    /** Свежая копия сообщения со связями и карточками ссылок. */
    public function reload(Message $message, ?User $me = null): Message
    {
        $message = $message->fresh()->load(self::MESSAGE_RELATIONS);

        app(ChatCardService::class)->attach(collect([$message]), $me);

        return $message;
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

        // Подарок и служебная отметка — события своего диалога, их не пересылают
        if ($message->kind !== Message::KIND_TEXT) {
            throw ValidationException::withMessages([
                'message' => ['Это сообщение нельзя переслать.'],
            ]);
        }

        $forwarded = DB::transaction(function () use ($message, $target, $me) {
            $copy = Message::create([
                'conversation_id' => $target->id,
                'user_id' => $me->id,
                'forwarded_from_user_id' => $message->forwarded_from_user_id ?: $message->user_id,
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
                    'is_voice' => $attachment->is_voice,
                    'duration' => $attachment->duration,
                ]);
            }

            $this->touchAfterWrite($target, $copy, $me);

            return $copy;
        });

        return $this->reload($forwarded, $me);
    }

    /**
     * Изменить текст своего сообщения (в течение суток).
     */
    public function updateMessage(Message $message, User $me, string $body): Message
    {
        if ($message->created_at && abs($message->created_at->diffInHours(now())) > 24) {
            throw ValidationException::withMessages([
                'body' => ['Редактировать сообщение можно в течение 24 часов.'],
            ]);
        }

        $stickers = app(\App\Domains\Shop\Services\StickerService::class);

        /*
         * Подарок, отметку и стикер не правят. А правка текста в метку
         * стикера обходила проверку покупки набора.
         */
        if ($message->kind !== Message::KIND_TEXT || preg_match($stickers::PATTERN, (string) $message->body)) {
            throw ValidationException::withMessages([
                'body' => ['Это сообщение нельзя изменить.'],
            ]);
        }

        $body = trim($body);

        if ($body === '' && $message->attachments()->doesntExist()) {
            throw ValidationException::withMessages([
                'body' => ['Сообщение не может быть пустым.'],
            ]);
        }

        $stickers->guard($me, $body);

        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        return $this->reload($message, $me);
    }

    public function deleteMessage(Message $message): void
    {
        $conversation = $message->conversation;

        $message->delete();

        /*
         * Время диалога — по последнему оставшемуся сообщению. Раньше
         * ставилось «сейчас», и удаление старого сообщения поднимало
         * диалог наверх списка.
         */
        $last = Message::where('conversation_id', $conversation->id)->max('created_at');

        $conversation->update(['last_message_at' => $last ?? $conversation->created_at]);
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

    /**
     * Загрузить пачку файлов одним запросом.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, MessageAttachment>
     */
    public function uploadAttachments(User $me, array $files): array
    {
        $result = [];

        foreach ($files as $file) {
            $result[] = $this->uploadAttachment($me, $file);
        }

        return $result;
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

        return $this->unreadQuery($me)
            ->whereIn('conversation_id', $conversationIds)
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
            ->select('id', 'username', 'avatar', 'tier', 'bridge_primary', 'bridge_rank_id', 'profile_mode', 'is_verified', 'name_effect', 'chat_bubble', 'profile_title', 'name_icon')
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
     * Поиск по тексту сообщений внутри диалога.
     *
     * Ищем по подстроке без учёта регистра. Сравнение через LIKE с
     * нижним регистром: SQLite и MySQL ведут себя одинаково, если
     * привести обе стороны.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchMessages(Conversation $conversation, User $me, string $query, int $limit = 40): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        /*
         * % и _ в запросе — обычные символы, а не шаблон LIKE. Экранируем
         * восклицательным знаком: обратная косая в MySQL сама экранирует
         * кавычку и ломает запрос.
         */
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('kind', Message::KIND_TEXT)
            ->whereNotNull('body')
            ->whereRaw("body like ? escape '!'", ['%' . $escaped . '%'])
            ->with(['user:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $online = app(PresenceService::class);

        return $messages->map(function (Message $message) use ($query, $online): array {
            $body = (string) $message->body;

            return [
                'id' => $message->id,
                'body' => $body,
                'created_at' => $message->created_at?->toIso8601String(),
                'user' => $message->user ? [
                    'id' => $message->user->id,
                    'username' => $message->user->username,
                    'avatar_url' => $message->user->avatar_url,
                    'is_online' => $online->isOnline((int) $message->user->id),
                ] : null,
                // Фрагмент вокруг совпадения: показывать всё сообщение незачем
                'snippet' => $this->snippet($body, $query),
            ];
        })->all();
    }

    /**
     * Фрагмент текста вокруг совпадения.
     *
     * Сообщения бывают длинными, а в списке нужен только кусок с
     * найденным словом.
     */
    private function snippet(string $body, string $query, int $radius = 45): string
    {
        $position = mb_stripos($body, $query);

        if ($position === false) {
            return mb_substr($body, 0, $radius * 2);
        }

        $start = max(0, $position - $radius);
        $fragment = mb_substr($body, $start, $radius * 2 + mb_strlen($query));

        return ($start > 0 ? '…' : '') . $fragment . (mb_strlen($body) > $start + mb_strlen($fragment) ? '…' : '');
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
            ->select('id', 'username', 'avatar', 'tier', 'bridge_primary', 'bridge_rank_id', 'profile_mode', 'is_verified', 'name_effect', 'chat_bubble', 'profile_title', 'name_icon')
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
            ->where('user_id', $message->user_id)
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
    private function presentMessages(Collection $messages, ?User $me): array
    {
        // Карточки ссылок — одной пачкой на всю страницу истории
        app(ChatCardService::class)->attach($messages, $me);

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
        return $conversation->load([...self::CONVERSATION_RELATIONS, 'pinnedMessage.user:id,username'])->toArray();
    }

    /** Последнее сообщение диалога для превью в списке (после удаления). */
    public function lastMessagePayload(Conversation $conversation): ?array
    {
        $last = $conversation->lastMessage()->with(['user:id,username,avatar', 'attachments'])->first();

        return $last?->toArray();
    }

    /**
     * Поставить или снять реакцию. Возвращает реакции сообщения
     * сгруппированно: эмодзи, сколько и стоит ли моя.
     */
    public function toggleReaction(Message $message, User $me, string $emoji): array
    {
        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $me->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::create(['message_id' => $message->id, 'user_id' => $me->id, 'emoji' => $emoji]);
        }

        return self::groupReactions($message->reactions()->get(['id', 'message_id', 'user_id', 'emoji']), $me->id);
    }

    /** Закрепить сообщение диалога (null — открепить). */
    public function pin(Conversation $conversation, ?Message $message): void
    {
        $conversation->forceFill(['pinned_message_id' => $message?->id])->save();
    }

    /**
     * [{emoji, count, mine, user_ids}] в порядке первой реакции.
     *
     * user_ids нужны событию по сокету: оно одно на всех, и «моя ли
     * реакция» каждый получатель считает сам.
     */
    public static function groupReactions($reactions, ?int $meId): array
    {
        return collect($reactions)
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => $meId !== null && $group->contains('user_id', $meId),
                'user_ids' => $group->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->values()
            ->all();
    }

    /* ----------------------------- Подарок ----------------------------- */

    /**
     * Подарок монет собеседнику прямо из личного диалога.
     *
     * Деньги идут через CoinService::gift — те же правила, что у подарка
     * из кошелька: только другу, мин/макс, дневной лимит и комиссия. В
     * ленте остаётся сообщение-посылка с суммой.
     */
    public function gift(Conversation $conversation, User $me, int $amount, ?string $note): Message
    {
        if (! $conversation->isDirect()) {
            throw ValidationException::withMessages([
                'amount' => ['Монеты можно подарить только в личном диалоге.'],
            ]);
        }

        $partnerId = $conversation->participants()->where('user_id', '!=', $me->id)->value('user_id');
        $partner = $partnerId ? User::find($partnerId) : null;

        if (! $partner) {
            throw ValidationException::withMessages(['amount' => ['Собеседник не найден.']]);
        }

        $note = trim((string) $note);

        // Подпись к подарку — обычный текст, метка стикера в ней не пройдёт
        app(\App\Domains\Shop\Services\StickerService::class)->guard($me, $note);

        $message = DB::transaction(function () use ($conversation, $me, $partner, $amount, $note) {
            try {
                [$received, $fee] = \App\Domains\Wallet\Services\CoinService::gift($me, $partner, $amount);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['amount' => [$e->getMessage()]]);
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $me->id,
                'kind' => Message::KIND_GIFT,
                'body' => $note,
                'meta' => ['amount' => $amount, 'received' => $received, 'fee' => $fee],
            ]);

            $this->touchAfterWrite($conversation, $message, $me);

            return $message;
        });

        return $this->reload($message, $me);
    }

    /* ----------------------------- Голосовые ----------------------------- */

    /**
     * Голосовое сообщение «про запас», как обычное вложение: id потом
     * уходит при отправке. Длительность пишет запись в браузере.
     */
    public function uploadVoice(User $me, UploadedFile $file, int $duration): MessageAttachment
    {
        $path = $file->store('chat/voice/' . now()->format('Y/m'), 'public');

        return MessageAttachment::create([
            'message_id' => null,
            'user_id' => $me->id,
            'original_name' => 'voice-' . now()->format('Ymd-His') . '.' . ($file->guessExtension() ?: 'webm'),
            'path' => $path,
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'is_image' => false,
            'is_voice' => true,
            'duration' => max(1, min(self::VOICE_MAX_SECONDS, $duration)),
        ]);
    }
}
