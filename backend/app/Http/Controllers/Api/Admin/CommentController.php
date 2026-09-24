<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClanEvent;
use App\Models\ClanEventComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClanEventComment::query()
            ->with([
                'user:id,username,avatar',
                'event:id,title,clan_id',
                'event.clan:id,name,tag',
            ]);

        if ($search = $request->query('search')) {
            $query->where('body', 'like', "%{$search}%");
        }

        $comments = $query->latest()->paginate(30);

        return response()->json($comments);
    }

    public function destroy(Request $request, ClanEventComment $comment): JsonResponse
    {
        $comment->delete();

        return response()->json(['ok' => true]);
    }

    public function events(Request $request): JsonResponse
    {
        $query = ClanEvent::query()
            ->with([
                'author:id,username,avatar',
                'clan:id,name,tag',
            ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $events = $query->latest()->paginate(30);

        return response()->json($events);
    }

    public function destroyEvent(Request $request, ClanEvent $event): JsonResponse
    {
        $event->delete();

        return response()->json(['ok' => true]);
    }
}
