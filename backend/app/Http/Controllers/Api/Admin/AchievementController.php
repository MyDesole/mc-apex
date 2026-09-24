<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function grant(Request $request, User $user, Achievement $achievement): JsonResponse
    {
        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return response()->json(['message' => 'Уже есть.'], 422);
        }

        $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

        $user->notify(new \App\Notifications\AchievementGrantedNotification($achievement));

        return response()->json(['ok' => true]);
    }

    public function revoke(Request $request, User $user, Achievement $achievement): JsonResponse
    {
        $user->achievements()->detach($achievement->id);

        return response()->json(['ok' => true]);
    }
}
