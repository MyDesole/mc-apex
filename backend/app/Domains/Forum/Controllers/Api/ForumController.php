<?php

namespace App\Domains\Forum\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Forum\Requests\Forum\CreateReplyRequest;
use App\Domains\Forum\Requests\Forum\CreateTopicRequest;
use App\Domains\Forum\Requests\Forum\ForumSearchRequest;
use App\Domains\Forum\Requests\Forum\TopicListRequest;
use App\Domains\Forum\Requests\Forum\UpdateReplyRequest;
use App\Domains\Forum\Requests\Forum\UpdateTopicRequest;
use App\Domains\Forum\Requests\Forum\UploadForumAttachmentRequest;
use App\Domains\Forum\Models\ForumAttachment;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Forum\Services\ForumService;
use App\Domains\Forum\Resources\ForumReplyResource;
use App\Domains\Forum\Resources\ForumTopicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Форум. Контроллер тонкий: валидация — в FormRequest, права — в политиках,
 * логика — в ForumService, формат ответа — в ForumPresenter.
 */
class ForumController extends Controller
{
    public function __construct(
        private readonly ForumService $forum,
    ) {
    }

    /* ----------------------------- Разделы ----------------------------- */

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->forum->categories($request->user()));
    }

    /* ----------------------------- Темы ----------------------------- */

    public function topics(TopicListRequest $request): JsonResponse
    {
        return response()->json($this->forum->topics($request->validated()));
    }

    public function store(CreateTopicRequest $request): JsonResponse
    {
        $data = $request->validated();

        $topic = $this->forum->createTopic(
            author: $request->user(),
            categoryId: (int) $data['category_id'],
            title: $data['title'],
            body: $data['body'],
            attachmentIds: $data['attachments'] ?? [],
        );

        return response()->json([
            'topic' => new ForumTopicResource($topic),
        ], 201);
    }

    public function show(Request $request, ForumTopic $topic): JsonResponse
    {
        return response()->json(
            $this->forum->showTopic($topic, $request->user(), $request->ip())
        );
    }

    public function update(UpdateTopicRequest $request, ForumTopic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        $this->forum->assertWithinEditWindow(
            $topic->author_id,
            $topic->created_at,
            $request->user(),
            'тему'
        );

        $updated = $this->forum->updateTopic($topic, $request->validated());

        return response()->json([
            'topic' => new ForumTopicResource($updated),
        ]);
    }

    public function destroy(Request $request, ForumTopic $topic): JsonResponse
    {
        $this->authorize('delete', $topic);

        $this->forum->deleteTopic($topic, $request->user());

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Ответы ----------------------------- */

    public function reply(CreateReplyRequest $request, ForumTopic $topic): JsonResponse
    {
        $data = $request->validated();

        $reply = $this->forum->addReply(
            topic: $topic,
            author: $request->user(),
            body: $data['body'],
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            attachmentIds: $data['attachments'] ?? [],
        );

        return response()->json([
            'reply' => new ForumReplyResource($reply),
        ], 201);
    }

    public function updateReply(UpdateReplyRequest $request, ForumReply $reply): JsonResponse
    {
        $this->authorize('update', $reply);

        $this->forum->assertWithinEditWindow(
            $reply->author_id,
            $reply->created_at,
            $request->user()
        );

        $updated = $this->forum->updateReply($reply, $request->string('body')->toString());

        return response()->json([
            'reply' => new ForumReplyResource($updated),
        ]);
    }

    public function destroyReply(Request $request, ForumReply $reply): JsonResponse
    {
        $this->authorize('delete', $reply);

        $removed = $this->forum->deleteReply($reply, $request->user());

        return response()->json(['ok' => true, 'removed' => $removed]);
    }

    /* ----------------------------- Лайки и файлы ----------------------------- */

    public function like(Request $request, string $type, int $id): JsonResponse
    {
        return response()->json(
            $this->forum->toggleLike($request->user(), $type, $id)
        );
    }

    public function upload(UploadForumAttachmentRequest $request): JsonResponse
    {
        $attachment = $this->forum->uploadAttachment($request->user(), $request->file('file'));

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

    public function download(Request $request, ForumAttachment $attachment)
    {
        abort_unless(Storage::disk('public')->exists($attachment->path), 404);

        return Storage::disk('public')->download($attachment->path, $attachment->original_name);
    }

    public function search(ForumSearchRequest $request): JsonResponse
    {
        return response()->json(
            $this->forum->search($request->string('q')->toString())
        );
    }

    /* ----------------------------- Внутреннее ----------------------------- */

    /**
     * Автор может править своё только в течение суток; модератор — всегда.
     * Это бизнес-правило, поэтому 422, а не 403.
     */
}
