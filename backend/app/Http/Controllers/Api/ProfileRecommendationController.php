<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfileRecommendation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileRecommendationController extends Controller
{
    /**
     * Список отзывов о юзере — публичный.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        $recommendations = ProfileRecommendation::where('target_id', $user->id)
            ->where('is_hidden', false)
            ->with('author:id,username,avatar,tier,is_verified,accent_color,banner_color')
            ->latest()
            ->get();

        // мой отзыв о нём (если я авторизован)
        $myRecommendation = null;
        if ($request->user()) {
            $myRecommendation = ProfileRecommendation::where('target_id', $user->id)
                ->where('author_id', $request->user()->id)
                ->first();
        }

        return response()->json([
            'recommendations' => $recommendations,
            'my_recommendation' => $myRecommendation,
        ]);
    }

    /**
     * Создать/обновить отзыв.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        if ($me->id === $user->id) {
            throw ValidationException::withMessages([
                'body' => ['Нельзя оставить отзыв самому себе.'],
            ]);
        }

        if (!$me->isFriendsWith($user->id)) {
            throw ValidationException::withMessages([
                'body' => ['Оставлять отзывы могут только друзья.'],
            ]);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:280'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $rec = ProfileRecommendation::updateOrCreate(
            [
                'author_id' => $me->id,
                'target_id' => $user->id,
            ],
            [
                'body' => $validated['body'],
                'rating' => $validated['rating'] ?? null,
                'is_hidden' => false,
            ]
        );

        $rec->load('author:id,username,avatar,tier,is_verified,accent_color,banner_color');

        return response()->json([
            'recommendation' => $rec,
            'created' => $rec->wasRecentlyCreated,
        ], $rec->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Удалить свой отзыв.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $rec = ProfileRecommendation::where('target_id', $user->id)
            ->where('author_id', $request->user()->id)
            ->first();

        if (!$rec) {
            abort(404);
        }

        $rec->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Скрыть отзыв у себя (только цель отзыва).
     */
    public function hide(Request $request, ProfileRecommendation $recommendation): JsonResponse
    {
        abort_unless(
            $recommendation->target_id === $request->user()->id,
            403,
            'Скрывать отзыв может только владелец профиля.'
        );

        $recommendation->update(['is_hidden' => true]);

        return response()->json(['ok' => true]);
    }
}
