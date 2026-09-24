<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $news = News::where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with('author:id,username,avatar')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json($news);
    }

    public function show(News $news): JsonResponse
    {
        if (!$news->is_published) {
            $user = auth()->user();
            if (!$user || !$user->isAdmin()) {
                abort(404);
            }
        }

        $news->load('author:id,username,avatar');

        $prev = News::where('is_published', true)
            ->where('id', '<', $news->id)
            ->orderByDesc('id')
            ->first(['id', 'title']);

        $next = News::where('is_published', true)
            ->where('id', '>', $news->id)
            ->orderBy('id')
            ->first(['id', 'title']);

        return response()->json([
            'news' => $news,
            'prev' => $prev,
            'next' => $next,
        ]);
    }
}
