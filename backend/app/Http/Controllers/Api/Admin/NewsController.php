<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $news = News::with('author:id,username,avatar')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($news);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'type' => ['required', 'in:news,update,event,announcement'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('cover')) {
            $validated['cover'] = $request->file('cover')->store('news', 'public');
        }

        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        $validated['author_id'] = $request->user()->id;
        $validated['published_at'] = $validated['published_at'] ?? now();

        $news = News::create($validated);

        return response()->json(['news' => $news], 201);
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'in:news,update,event,announcement'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('cover')) {
            if ($news->cover) \Storage::disk('public')->delete($news->cover);
            $validated['cover'] = $request->file('cover')->store('news', 'public');
        }

        $news->update($validated);

        return response()->json(['news' => $news->fresh()]);
    }

    public function destroy(Request $request, News $news): JsonResponse
    {
        if ($news->cover) \Storage::disk('public')->delete($news->cover);
        $news->delete();

        return response()->json(['ok' => true]);
    }
}
