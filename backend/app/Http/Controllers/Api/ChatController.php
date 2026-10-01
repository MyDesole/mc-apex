<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ConversationHistoryRequest;
use App\Http\Requests\Chat\ForwardMessageRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\StartClanRequest;
use App\Http\Requests\Chat\StartDirectRequest;
use App\Http\Requests\Chat\UpdateMessageRequest;
use App\Http\Requests\Chat\UploadAttachmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\ChatService;
use App\Http\Resources\MessageResource;
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
                'author:id,username,avatar,tier,is_verified',
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
            forwardedFromUserId: $request->filled('forwarded_from_user_id')
                ? $request->integer('forwarded_from_user_id')
                : null,
            attachmentIds: $request->input('attachments', []),
        );

        broadcast(new MessageSent($message))->toOthers();

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

        broadcast(new MessageSent($forwarded))->toOthers();

        return response()->json([
            'message' => new MessageResource($forwarded),
        ], 201);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $updated = $this->chat->updateMessage($message, $request->string('body')->toString());

        return response()->json([
            'message' => new MessageResource($updated),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $this->chat->deleteMessage($message);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Вложения ----------------------------- */

    public function uploadAttachment(UploadAttachmentRequest $request): JsonResponse
    {
        $attachment = $this->chat->uploadAttachment($request->user(), $request->file('file'));

        return response()->json([
            'attachment' => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'url' => $attachment->url,
                'size' => $attachment->human_size,
                'is_image' => $attachment->is_image,
            ],
        ], 201);
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $this->authorize('download', $attachment);

        $path = $this->chat->attachmentPath($attachment);

        return \Illuminate\Support\Facades\Storage::disk('public')
            ->download($path, $attachment->original_name);
    }

    /* ----------------------------- Прочтения ----------------------------- */

    public function markRead(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $this->chat->markMessageRead($message, $request->user());

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
}
