<?php

namespace App\Domains\Achievements\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Achievements\Requests\Admin\AchievementRequest;
use App\Domains\Achievements\Models\Achievement;
use App\Domains\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AchievementController extends Controller
{
    /**
     * Список всех ачивок для админки.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Achievement::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($rarity = $request->query('rarity')) {
            $query->where('rarity', $rarity);
        }

        if ($request->query('system') === '1') {
            $query->where('is_system', true);
        } elseif ($request->query('custom') === '1') {
            $query->where('is_system', false);
        }

        $achievements = $query->orderBy('points')->get();

        // добавляем кол-во выданных
        $achievements->transform(function ($a) {
            $a->granted_count = \DB::table('user_achievements')
                ->where('achievement_id', $a->id)
                ->count();
            return $a;
        });

        return response()->json(['achievements' => $achievements]);
    }

    /**
     * Создать новую ачивку.
     */
    public function store(AchievementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // если code не задан — генерируем
        $validated['code'] = $validated['code']
            ?? 'custom_' . Str::random(8);

        $validated['is_system'] = false;
        $validated['is_active'] = true;

        $achievement = Achievement::create($validated);

        return response()->json(['achievement' => $achievement], 201);
    }

    /**
     * Обновить ачивку.
     */
    public function update(AchievementRequest $request, Achievement $achievement): JsonResponse
    {
        // системные ачивки нельзя переименовать код, но можно менять
        // название, иконку, цвет, очки
        $validated = $request->validated();

        // системную ачивку нельзя деактивировать
        if ($achievement->is_system && isset($validated['is_active']) && !$validated['is_active']) {
            return response()->json([
                'message' => 'Системную ачивку нельзя отключить.',
            ], 422);
        }

        $achievement->update($validated);

        return response()->json(['achievement' => $achievement->fresh()]);
    }

    /**
     * Удалить ачивку.
     */
    public function destroy(Request $request, Achievement $achievement): JsonResponse
    {
        if ($achievement->is_system) {
            return response()->json([
                'message' => 'Системную ачивку нельзя удалить. Можно только отключить.',
            ], 422);
        }

        // отвязываем от юзеров (на всякий случай)
        $achievement->users()->detach();
        $achievement->delete();

        return response()->json(['ok' => true]);
    }

    // === Выдача / отзыв у юзера (было раньше) ===

    public function grant(Request $request, User $user, Achievement $achievement): JsonResponse
    {
        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return response()->json(['message' => 'Уже есть.'], 422);
        }

        $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

        $user->notify(new \App\Domains\Achievements\Notifications\AchievementGrantedNotification($achievement));

        return response()->json(['ok' => true]);
    }

    public function revoke(Request $request, User $user, Achievement $achievement): JsonResponse
    {
        $user->achievements()->detach($achievement->id);

        return response()->json(['ok' => true]);
    }
}
