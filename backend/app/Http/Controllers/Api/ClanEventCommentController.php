<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clan\ClanEventCommentRequest;
use App\Models\Clan;
use App\Models\ClanEvent;
use App\Models\ClanEventComment;
use App\Services\ClanEventCommentService;
use App\Services\ClanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Комментарии к событиям клана. Логика — в ClanEventCommentService,
 * проверка руководства — в ClanService.
 */
class ClanEventCommentController extends Controller
{
    public function __construct(
        private readonly ClanEventCommentService $comments,
        private readonly ClanService $clans,
    ) {
    }

    public function index(Request $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        return response()->json([
            'comments' => $this->comments->list($clan, $event, $request->user()),
        ]);
    }

    public function store(ClanEventCommentRequest $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        $comment = $this->comments->create(
            clan: $clan,
            event: $event,
            user: $request->user(),
            data: $request->validated(),
        );

        return response()->json(['comment' => $comment], 201);
    }

    public function destroy(
        Request $request,
        Clan $clan,
        ClanEvent $event,
        ClanEventComment $comment,
    ): JsonResponse {
        $this->comments->delete($clan, $event, $comment, $request->user(), $this->clans);

        return response()->json(['ok' => true]);
    }
}
