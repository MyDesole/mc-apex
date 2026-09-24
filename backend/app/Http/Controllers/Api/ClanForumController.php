<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClanForumReply;
use App\Models\ClanForumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanForumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clan = $request->attributes->get('clan');

        $topics = ClanForumTopic::where('clan_id', $clan->id)
            ->with(['author:id,username,avatar', 'lastReplyUser:id,username'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_reply_at')
            ->paginate(20);

        return response()->json($topics);
    }

    public function store(Request $request): JsonResponse
    {
        $clan = $request->attributes->get('clan');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $topic = ClanForumTopic::create([
            'clan_id' => $clan->id,
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json(['topic' => $topic->load('author:id,username,avatar')], 201);
    }

    public function show(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        abort_if($topic->clan_id !== $clan->id, 404);

        $topic->increment('views');

        $topic->load([
            'author:id,username,avatar',
            'replies.author:id,username,avatar',
        ]);

        return response()->json(['topic' => $topic]);
    }

    public function reply(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        abort_if($topic->clan_id !== $clan->id, 404);
        abort_if($topic->is_locked, 422, 'Топик закрыт.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:clan_forum_replies,id'],
        ]);

        $reply = ClanForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        $topic->update([
            'replies_count' => $topic->replies()->count(),
            'last_reply_at' => now(),
            'last_reply_user_id' => $request->user()->id,
        ]);

        return response()->json(['reply' => $reply->load('author:id,username,avatar')], 201);
    }

    public function pin(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        abort_if($topic->clan_id !== $clan->id, 404);

        $topic->update(['is_pinned' => !$topic->is_pinned]);

        return response()->json(['topic' => $topic->fresh()]);
    }

    public function lock(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        abort_if($topic->clan_id !== $clan->id, 404);

        $topic->update(['is_locked' => !$topic->is_locked]);

        return response()->json(['topic' => $topic->fresh()]);
    }

    public function destroy(Request $request, ClanForumTopic $topic): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        $user = $request->user();
        $membership = $request->attributes->get('clan_membership');

        abort_if($topic->clan_id !== $clan->id, 404);

        $canDelete = $topic->author_id === $user->id
            || $membership->role === 'leader'
            || $membership->role === 'officer';

        abort_unless($canDelete, 403);

        $topic->delete();

        return response()->json(['ok' => true]);
    }
}
