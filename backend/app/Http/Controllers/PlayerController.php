<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\PlayerAspect;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = User::query()
            ->where('id', '!=', $me->id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        $players = $query->orderByDesc('tier_score')
            ->paginate(20);

        // статусы дружбы (как у тебя было)
        $ids = collect($players->items())->pluck('id')->all();

        $friendships = Friendship::where(function ($q) use ($me, $ids) {
            $q->where('user_id', $me->id)->whereIn('friend_id', $ids);
        })->orWhere(function ($q) use ($me, $ids) {
            $q->where('friend_id', $me->id)->whereIn('user_id', $ids);
        })->get();

        $statusMap = [];
        foreach ($friendships as $f) {
            $otherId = $f->user_id === $me->id ? $f->friend_id : $f->user_id;
            $statusMap[$otherId] = [
                'status' => $f->status,
                'initiated_by_me' => $f->user_id === $me->id,
            ];
        }

        $players->getCollection()->transform(function ($player) use ($statusMap) {
            $player->friendship = $statusMap[$player->id] ?? null;
            return $player;
        });

        return response()->json($players);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load([
            'aspects',
            'tierTests' => fn ($q) => $q->latest()->limit(10),
            'clanMember.clan' => fn ($q) => $q->withCount('members'),
        ]);

        $me = $request->user();

        $friendship = Friendship::where(function ($q) use ($me, $user) {
            $q->where('user_id', $me->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $me->id);
        })->first();

        $position = null;
        $total = 0;

        if ($user->tier_score > 0) {
            $position = User::where('tier_score', '>', $user->tier_score)->count() + 1;
            $total = User::where('tier_score', '>', 0)->count();
        }

        return response()->json([
            'user' => $user,
            'friendship' => $friendship ? [
                'status' => $friendship->status,
                'initiated_by_me' => $friendship->user_id === $me->id,
            ] : null,
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
            'banner_color' => ['nullable', 'string', 'max:16'],

            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'socials' => ['nullable', 'array'],
            'socials.discord' => ['nullable', 'string', 'max:255'],
            'socials.telegram' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.vk' => ['nullable', 'string', 'max:255'],
            'socials.website' => ['nullable', 'string', 'max:255'],  // ← без 'url', чтобы не резало
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request
                ->file('avatar')
                ->store("users/{$user->id}", 'public');
        }

        if ($request->hasFile('cover')) {
            if ($user->cover_path) {
                Storage::disk('public')->delete($user->cover_path);
            }

            $validated['cover_path'] = $request
                ->file('cover')
                ->store("users/{$user->id}/covers", 'public');
        }

        unset($validated['cover']);

        $user->update($validated);

        return response()->json(['user' => $user->fresh()]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function removeCover(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->cover_path) {
            Storage::disk('public')->delete($user->cover_path);
            $user->update(['cover_path' => null]);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function updateAspects(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:pvp,bedwars'],
            'block_placing' => ['required', 'integer', 'min:0', 'max:10'],
            'rotka' => ['required', 'integer', 'min:0', 'max:10'],
            'movement' => ['required', 'integer', 'min:0', 'max:10'],
            'building' => ['required', 'integer', 'min:0', 'max:10'],
            'ppl' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $aspect = \App\Models\PlayerAspect::updateOrCreate(
            ['user_id' => $request->user()->id, 'mode' => $validated['mode']],
            $validated
        );

        $user = $request->user();
        $user->tier_score = $aspect->percent();
        $user->tier = $aspect->tier();
        $user->save();

        return response()->json(['aspect' => $aspect]);
    }
}
