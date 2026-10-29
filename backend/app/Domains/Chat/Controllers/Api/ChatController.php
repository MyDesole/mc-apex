<?php

namespace App\Domains\Chat\Controllers\Api;

use App\Domains\Chat\Events\ConversationChanged;
use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Services\ChatGroupService;
use App\Domains\Users\Models\User;
use App\Domains\Chat\Events\MessagesRead;
use App\Domains\Chat\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Support\Broadcasting\SafeBroadcast;
use App\Domains\Chat\Requests\Chat\ConversationHistoryRequest;
use App\Domains\Chat\Requests\Chat\ForwardMessageRequest;
use App\Domains\Chat\Requests\Chat\SendMessageRequest;
use App\Domains\Chat\Requests\Chat\StartClanRequest;
use App\Domains\Chat\Requests\Chat\StartDirectRequest;
use App\Domains\Chat\Requests\Chat\UpdateMessageRequest;
use App\Domains\Chat\Requests\Chat\UploadAttachmentRequest;
use App\Http\Responses\ApiResponse;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Chat\Services\ChatService;
use App\Domains\Chat\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Чат. Контроллер тонкий: принимает запрос, проверяет права, отдаёт результат.
 * Вся логика — в ChatService, формат ответа — в MessagePresenter.
 */
class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ChatService $chat,
    ) {
    }

    /* ----------------------------- Диалоги ----------------------------- */

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->chat->listConversations($request->user());

        return response()->json(['conversations' => $conversations]);
    }

    public function show(ConversationHistoryRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $me = $request->user();

        $history = $this->chat->history(
            conversation: $conversation,
            me: $me,
            beforeId: $request->filled('before_id') ? $request->integer('before_id') : null,
            afterId: $request->filled('after_id') ? $request->integer('after_id') : null,
            limit: $request->integer('limit') ?: ChatService::PAGE_SIZE,
            markRead: $request->boolean('mark_read', true),
        );

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation),
            ...$history,
            'other_participants_count' => $conversation->participants()
                ->where('user_id', '!=', $me->id)
                ->count(),
        ]);
    }

    public function startDirect(StartDirectRequest $request): JsonResponse
    {
        $conversation = $this->chat->startDirect($request->user(), $request->integer('user_id'));

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation),
        ], 201);
    }

    public function startClan(StartClanRequest $request): JsonResponse
    {
        $conversation = $this->chat->startClan($request->user(), $request->integer('clan_id'));

        return response()->json([
            'conversation' => $conversation->load([
                'clan:id,name,tag,banner_color,avatar',
                'author:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified',
            ]),
        ], 201);
    }

    /* ----------------------------- Сообщения ----------------------------- */

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $message = $this->chat->send(
            conversation: $conversation,
            me: $request->user(),
            body: $request->input('body'),
            replyToId: $request->filled('reply_to_id') ? $request->integer('reply_to_id') : null,
            attachmentIds: $request->input('attachments', []),
        );

        SafeBroadcast::send(new MessageSent($message), toOthers: true);

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    public function forward(ForwardMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('forward', $message);

        $target = Conversation::findOrFail($request->integer('conversation_id'));

        $this->authorize('send', $target);

        $forwarded = $this->chat->forward($message, $target, $request->user());

        SafeBroadcast::send(new MessageSent($forwarded), toOthers: true);

        return response()->json([
            'message' => new MessageResource($forwarded),
        ], 201);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $updated = $this->chat->updateMessage($message, $request->user(), $request->string('body')->toString());

        $this->changed($updated->conversation, 'message.updated', [
            'message' => (new MessageResource($updated))->resolve(),
        ]);

        return response()->json([
            'message' => new MessageResource($updated),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $conversation = $message->conversation;
        $messageId = $message->id;

        $this->chat->deleteMessage($message);

        // Собеседник видит удаление сразу, превью списка — по новому последнему
        $this->changed($conversation->fresh(), 'message.deleted', [
            'message_id' => $messageId,
            'last_message' => $this->chat->lastMessagePayload($conversation),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Реакция на сообщение: поставить или снять. */
    public function react(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $data = $request->validate(['emoji' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Domains\Chat\Models\MessageReaction::EMOJI)]]);

        $reactions = $this->chat->toggleReaction($message, $request->user(), $data['emoji']);

        $this->changed($message->conversation, 'reactions', [
            'message_id' => $message->id,
            'reactions' => $reactions,
        ]);

        return response()->json(['reactions' => $reactions]);
    }

    /** Подарок монет собеседнику прямо из личного диалога. */
    public function gift(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $message = $this->chat->gift($conversation, $request->user(), (int) $data['amount'], $data['note'] ?? null);

        SafeBroadcast::send(new MessageSent($message), toOthers: true);

        return response()->json([
            'message' => new MessageResource($message),
            'balance' => (int) $request->user()->fresh()->apex_coins,
        ], 201);
    }

    /** Голосовое: загрузка «про запас», id уходит при отправке. */
    public function uploadVoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Браузеры пишут по-разному: Chrome и Firefox — webm/ogg, Safari — mp4
            'file' => ['required', 'file', 'max:4096', 'mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/aac,audio/mpeg'],
            'duration' => ['required', 'integer', 'min:1', 'max:' . ChatService::VOICE_MAX_SECONDS],
        ], [
            'file.mimetypes' => 'Не удалось распознать запись — попробуй ещё раз.',
            'file.max' => 'Запись слишком большая.',
        ]);

        $file = $this->chat->uploadVoice($request->user(), $data['file'], (int) $data['duration']);

        return response()->json([
            'attachment' => [
                'id' => $file->id,
                'name' => $file->original_name,
                'url' => $file->url,
                'size' => $file->human_size,
                'is_image' => false,
                'is_voice' => true,
                'duration' => $file->duration,
            ],
        ], 201);
    }

    /**
     * Сообщить участникам об изменении в диалоге.
     *
     * Себе тоже: другие вкладки того же игрока должны обновиться. Эта
     * вкладка отсеивается по X-Socket-ID.
     */
    private function changed(Conversation $conversation, string $kind, array $data = [], array $extraUserIds = []): void
    {
        SafeBroadcast::send(new ConversationChanged($conversation, $kind, $data, $extraUserIds), toOthers: true);
    }

    /** Закрепить сообщение в диалоге или открепить (message_id = null). */
    public function pin(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate(['message_id' => ['present', 'nullable', 'integer']]);

        $message = $data['message_id']
            ? Message::where('conversation_id', $conversation->id)->findOrFail($data['message_id'])
            : null;

        $this->chat->pin($conversation, $message);

        $payload = $this->chat->conversationPayload($conversation->fresh());

        $this->changed($conversation, 'pinned', [
            'pinned_message_id' => $payload['pinned_message_id'] ?? null,
            'pinned_message' => $payload['pinned_message'] ?? null,
        ]);

        return response()->json(['conversation' => $payload]);
    }

    /* ----------------------------- Беседы ----------------------------- */

    public function createGroup(Request $request, ChatGroupService $groups): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:64'],
            'user_ids' => ['required', 'array', 'min:1', 'max:' . ChatGroupService::MAX_MEMBERS],
            'user_ids.*' => ['integer'],
        ]);

        [$conversation, $message] = $groups->create($request->user(), $data['title'], $data['user_ids']);

        SafeBroadcast::send(new MessageSent($message), toOthers: true);

        return response()->json([
            'conversation' => $this->chat->conversationPayload($conversation),
        ], 201);
    }

    public function addMembers(Request $request, Conversation $conversation, ChatGroupService $groups): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:' . ChatGroupService::MAX_MEMBERS],
            'user_ids.*' => ['integer'],
        ]);

        $message = $groups->addMembers($conversation, $request->user(), $data['user_ids']);

        return $this->groupChanged($conversation, $message);
    }

    public function removeMember(Request $request, Conversation $conversation, User $user, ChatGroupService $groups): JsonResponse
    {
        $this->authorize('send', $conversation);

        $message = $groups->removeMember($conversation, $request->user(), $user);

        // Ушёл последний — беседы больше нет
        if (! $message) {
            return response()->json(['ok' => true, 'conversation' => null]);
        }

        return $this->groupChanged($conversation, $message, [$user->id]);
    }

    public function renameGroup(Request $request, Conversation $conversation, ChatGroupService $groups): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate(['title' => ['required', 'string', 'max:64']]);

        $message = $groups->rename($conversation, $request->user(), $data['title']);

        return $this->groupChanged($conversation, $message);
    }

    /**
     * Беседа изменилась: служебная отметка уходит в ленту, а новый состав
     * и название — всем участникам и тому, кого исключили.
     */
    private function groupChanged(Conversation $conversation, Message $message, array $extraUserIds = []): JsonResponse
    {
        SafeBroadcast::send(new MessageSent($message), toOthers: true);

        $payload = $this->chat->conversationPayload($conversation->fresh());

        $this->changed($conversation, 'conversation', ['conversation' => $payload], $extraUserIds);

        return response()->json([
            'conversation' => $payload,
            'message' => new MessageResource($message),
        ]);
    }

    /* ----------------------------- Вложения ----------------------------- */

    /**
     * Загрузка вложений: один файл (file) или пачка (files).
     *
     * Возвращаем оба ключа, чтобы старый клиент продолжал работать:
     * attachment — первый файл, attachments — весь список.
     */
    public function uploadAttachment(UploadAttachmentRequest $request): JsonResponse
    {
        $attachments = $this->chat->uploadAttachments(
            $request->user(),
            $request->filesToStore()
        );

        $payload = array_map(fn (MessageAttachment $file) => [
            'id' => $file->id,
            'name' => $file->original_name,
            'url' => $file->url,
            'size' => $file->human_size,
            'is_image' => $file->is_image,
        ], $attachments);

        return response()->json([
            'attachments' => $payload,
            // Обратная совместимость: раньше ответ содержал один файл
            'attachment' => $payload[0] ?? null,
        ], 201);
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $this->authorize('download', $attachment);

        $path = $this->chat->attachmentPath($attachment);

        return \Illuminate\Support\Facades\Storage::disk('public')
            ->download($path, $attachment->original_name);
    }

    /**
     * Собеседник печатает.
     *
     * Страница зовёт это с промежутком, а не на каждую букву. Ответ
     * пустой: получатели узнают о печати через канал.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        SafeBroadcast::send(new UserTyping($conversation, $request->user()));

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Прочтения ----------------------------- */

    public function markRead(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $this->chat->markMessageRead($message, $request->user());

        /*
         * Автору сообщения — чтобы галочка встала сразу. Собираем через
         * диалог: он знает всех участников.
         */
        SafeBroadcast::send(MessagesRead::forConversation($message->conversation, $request->user()));

        // Формат плоский (unread_count в корне) — так его читает фронтенд
        return response()->json([
            'ok' => true,
            'unread_count' => $this->chat->unreadCount($request->user()),
        ]);
    }

    public function markConversationRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('markRead', $conversation);

        $this->chat->markRead($conversation, $request->user());

        /*
         * Сообщаем автору: иначе галочка прочтения появлялась только
         * после обновления страницы.
         */
        SafeBroadcast::send(MessagesRead::forConversation($conversation, $request->user()));

        return response()->json([
            'ok' => true,
            'unread_count' => $this->chat->unreadCount($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->chat->unreadCount($request->user()),
        ]);
    }

    /* ----------------------------- Поиск ----------------------------- */

    public function search(Request $request): JsonResponse
    {
        $result = $this->chat->search($request->user(), (string) $request->query('q', ''));

        return response()->json($result);
    }

    /**
     * Поиск по тексту сообщений внутри диалога.
     *
     * Доступ проверяется политикой: искать можно только в своём диалоге.
     */
    public function searchMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $this->chat->searchMessages(
            $conversation,
            $request->user(),
            (string) $request->query('q', ''),
        );

        return response()->json([
            'messages' => $messages,
            'total' => count($messages),
        ]);
    }
}
