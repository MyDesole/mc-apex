<?php

namespace App\Domains\Clan\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Clan\Requests\Forum\ClanReplyRequest;
use App\Domains\Clan\Requests\Forum\ClanTopicRequest;
use App\Domains\Clan\Models\ClanForumTopic;
use App\Domains\Users\Models\User;
use App\Domains\Clan\Services\ClanForumService;
use App\Domains\Clan\Support\ClanContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Форум клана. Контроллер тонкий: контекст — ClanContext,
 * правила и подсчёт ответов — ClanForumService.
 */
class ClanForumController extends Controller
{
    public function __construct(
        private readonly ClanForumService $forum,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->forum->topics(ClanContext::clan($request))
        );
    }

    public function store(ClanTopicRequest $request): JsonResponse
    {
        $topic = $this->forum->createTopic(
            clan: ClanContext::clan($request),
            author: $request->user(),
            data: $request->validated(),
        );

        return response()->json(['topic' => $topic], 201);
    }

    public function show(Request $request, ClanForumTopic $topic): JsonResponse
    {
        return response()->json([
            'topic' => $this->forum->showTopic(ClanContext::clan($request), $topic),
        ]);
    }

    public function reply(ClanReplyRequest $request, ClanForumTopic $topic): JsonResponse
    {
        $reply = $this->forum->addReply(
            clan: ClanContext::clan($request),
            topic: $topic,
            author: $request->user(),
            data: $request->validated(),
        );

        return response()->json(['reply' => $reply], 201);
    }

    public function pin(Request $request, ClanForumTopic $topic): JsonResponse
    {
        return response()->json([
            'topic' => $this->forum->togglePin(ClanContext::clan($request), $topic),
        ]);
    }

    public function lock(Request $request, ClanForumTopic $topic): JsonResponse
    {
        return response()->json([
            'topic' => $this->forum->toggleLock(ClanContext::clan($request), $topic),
        ]);
    }

    public function destroy(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $this->authorize('delete', $topic);

        $this->forum->deleteTopic(ClanContext::clan($request), $topic);

        return response()->json(['ok' => true]);
    }
}
