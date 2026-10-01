<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\AdminCategoryRequest;
use App\Models\ForumAttachment;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Модерация форума: разделы, закрепление, закрытие, удаление и восстановление тем.
 */
class ForumController extends Controller
{
    /* ------------------------------- Разделы ------------------------------- */

    public function categories(Request $request): JsonResponse
    {
        return response()->json([
            'categories' => ForumCategory::orderBy('sort_order')->get(),
            'policies' => [
                ['value' => 'all', 'label' => 'Все игроки'],
                ['value' => 'verified', 'label' => 'Только верифицированные'],
                ['value' => 'staff', 'label' => 'Только персонал'],
            ],
        ]);
    }

    public function storeCategory(AdminCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $category = ForumCategory::create($validated);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(AdminCategoryRequest $request, ForumCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['category' => $category->fresh()]);
    }

    public function destroyCategory(Request $request, ForumCategory $category): JsonResponse
    {
        $topics = $category->topics()->count();

        if ($topics > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => "В разделе {$topics} тем(ы). Удалить вместе с ними? Передай force=1 для подтверждения.",
                'topics_count' => $topics,
            ], 409);
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }

    /* -------------------------------- Темы -------------------------------- */

    public function topics(Request $request): JsonResponse
    {
        $query = ForumTopic::query()
            ->with(['author:id,username', 'category:id,name,color', 'deletedBy:id,username'])
            ->latest();

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->query('only_deleted') === '1') {
            $query->whereNotNull('deleted_at');
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        return response()->json($query->paginate(30));
    }

    public function pin(ForumTopic $topic): JsonResponse
    {
        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        return response()->json(['topic' => $topic->fresh()]);
    }

    public function lock(ForumTopic $topic): JsonResponse
    {
        $topic->update(['is_locked' => ! $topic->is_locked]);

        return response()->json(['topic' => $topic->fresh()]);
    }

    public function destroyTopic(Request $request, ForumTopic $topic): JsonResponse
    {
        $topic->update(['deleted_by' => $request->user()->id, 'deleted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function restoreTopic(ForumTopic $topic): JsonResponse
    {
        $topic->update(['deleted_at' => null, 'deleted_by' => null]);

        return response()->json(['topic' => $topic->fresh()]);
    }

    public function destroyReply(Request $request, ForumReply $reply): JsonResponse
    {
        $reply->update(['deleted_by' => $request->user()->id, 'deleted_at' => now()]);

        $topic = $reply->topic;

        if ($topic) {
            $topic->update([
                'replies_count' => ForumReply::where('topic_id', $topic->id)->visible()->count(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /* ------------------------------ Статистика ----------------------------- */

    public function stats(): JsonResponse
    {
        return response()->json([
            'categories' => ForumCategory::count(),
            'topics' => ForumTopic::visible()->count(),
            'deleted_topics' => ForumTopic::whereNotNull('deleted_at')->count(),
            'replies' => ForumReply::visible()->count(),
            'locked' => ForumTopic::visible()->where('is_locked', true)->count(),
            'pinned' => ForumTopic::visible()->where('is_pinned', true)->count(),
            'attachments' => ForumAttachment::count(),
        ]);
    }

}
