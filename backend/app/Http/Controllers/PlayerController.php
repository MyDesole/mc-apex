<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\PlayerAspect;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = User::query()
            ->select(['id', 'username', 'avatar', 'tier', 'tier_score', 'bio'])
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

        // Подтягиваем статусы дружбы одним запросом
        $ids = collect($players->items())->pluck('id')->all();

        $friendships = Friendship::where(function ($q) use ($me, $ids) {
            $q->where('user_id', $me->id)->whereIn('friend_id', $ids);
        })->orWhere(function ($q) use ($me, $ids) {
            $q->where('friend_id', $me->id)->whereIn('user_id', $ids);
        })->get();

        // Мапа: id другого юзера → статус
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
        ]);

        $me = $request->user();

        $friendship = Friendship::where(function ($q) use ($me, $user) {
            $q->where('user_id', $me->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $me->id);
        })->first();

        return response()->json([
            'user' => $user,
            'friendship' => $friendship ? [
                'status' => $friendship->status,
                'initiated_by_me' => $friendship->user_id === $me->id,
            ] : null,
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return response()->json(['user' => $request->user()]);
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
