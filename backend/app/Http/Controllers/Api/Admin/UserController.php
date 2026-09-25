<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['clanMember.clan:id,name,tag,banner_color'])
            ->withCount(['achievements']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($request->query('banned') === '1') {
            $query->where('is_banned', true);
        }

        $users = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($users);
    }

    public function verify(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:128'],
        ]);

        $user->update([
            'is_verified' => true,
            'verified_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['user' => $user->fresh()]);
    }

    public function verified(Request $request): JsonResponse
    {
        $query = User::query()
            ->select(['id', 'username', 'email', 'avatar', 'tier', 'role', 'is_verified', 'verified_reason', 'created_at'])
            ->with('clanMember.clan:id,name,tag,banner_color');

        if ($request->query('status') === 'verified') {
            $query->where('is_verified', true);
        } elseif ($request->query('status') === 'unverified') {
            $query->where('is_verified', false);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('is_verified')
            ->orderByDesc('updated_at')
            ->paginate(30);

        return response()->json($users);
    }

    /**
     * Выдать верификацию.
     */


    /**
     * Снять верификацию.
     */
    public function unverify(Request $request, User $user): JsonResponse
    {
        if (!$user->is_verified) {
            return response()->json(['message' => 'Уже не верифицирован.'], 422);
        }

        $user->update([
            'is_verified' => false,
            'verified_reason' => null,
        ]);

        return response()->json(['user' => $user->fresh()]);
    }



    public function show(Request $request, User $user): JsonResponse
    {
        $user->load([
            'aspectPvp',
            'aspectBedwars',
            'achievements',
            'clanMember.clan',
            'tierTests' => fn ($q) => $q->latest()->limit(20),
        ]);
        return response()->json([
            'user' => array_merge($user->toArray(), [
                'aspects' => [
                    'pvp' => $user->aspectPvp,
                    'bedwars' => $user->aspectBedwars,
                ],
            ]),
            'achievement_points' => $user->achievementPoints(),
        ]);
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Нельзя забанить себя.');
        abort_if($user->isAdmin(), 422, 'Нельзя забанить администратора.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);

        $user->update([
            'is_banned' => true,
            'ban_reason' => $validated['reason'],
            'banned_until' => $validated['until'] ?? null,
            'banned_by' => $request->user()->id,
        ]);

        return response()->json(['user' => $user->fresh()]);
    }

    public function unban(Request $request, User $user): JsonResponse
    {
        $user->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_until' => null,
            'banned_by' => null,
        ]);

        return response()->json(['user' => $user->fresh()]);
    }
}
