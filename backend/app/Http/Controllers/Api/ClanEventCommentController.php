<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\ClanEvent;
use App\Models\ClanEventComment;
use App\Models\ClanMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanEventCommentController extends Controller
{
    public function index(Request $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        abort_if($event->clan_id !== $clan->id, 404);

        // только участники клана видят комментарии
        abort_unless($clan->isMember($request->user()->id), 403);

        $comments = ClanEventComment::where('clan_event_id', $event->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,username,avatar,tier',
                'replies.user:id,username,avatar,tier',
            ])
            ->latest()
            ->get();

        return response()->json(['comments' => $comments]);
    }

    public function store(Request $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        abort_if($event->clan_id !== $clan->id, 404);
        abort_unless($clan->isMember($request->user()->id), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            'parent_id' => ['nullable', 'exists:clan_event_comments,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = ClanEventComment::find($validated['parent_id']);
            abort_if($parent->clan_event_id !== $event->id, 422, 'Родительский комментарий из другого ивента.');
        }

        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => trim($validated['body']),
        ]);

        return response()->json([
            'comment' => $comment->load('user:id,username,avatar,tier'),
        ], 201);
    }

    public function destroy(Request $request, Clan $clan, ClanEvent $event, ClanEventComment $comment): JsonResponse
    {
        abort_if($event->clan_id !== $clan->id, 404);
        abort_if($comment->clan_event_id !== $event->id, 404);

        $user = $request->user();

        $canDelete = $comment->user_id === $user->id
            || $clan->isLeader($user->id)
            || $this->isOfficer($clan, $user->id);

        abort_unless($canDelete, 403);

        $comment->delete();

        return response()->json(['ok' => true]);
    }

    private function isOfficer(Clan $clan, int $userId): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $userId)
            ->where('role', 'officer')
            ->exists();
    }
}
