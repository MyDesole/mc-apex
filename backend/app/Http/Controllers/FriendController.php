<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendAcceptedNotification;
use App\Notifications\FriendRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $friends = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('friend_id', $userId))
            ->with(['user:id,username,avatar,tier', 'friend:id,username,avatar,tier'])
            ->get()
            ->map(fn ($f) => $f->user_id === $userId ? $f->friend : $f->user)
            ->values();

        $incoming = Friendship::where('friend_id', $userId)
            ->where('status', 'pending')
            ->with('user:id,username,avatar,tier')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'user' => $f->user,
                'created_at' => $f->created_at,
            ])
            ->values();

        $outgoing = Friendship::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('friend:id,username,avatar,tier')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'user' => $f->friend,
                'created_at' => $f->created_at,
            ])
            ->values();

        return response()->json([
            'friends' => $friends,
            'incoming_requests' => $incoming,
            'outgoing_requests' => $outgoing,
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Нельзя добавить себя.');

        $existing = Friendship::where(function ($q) use ($request, $user) {
            $q->where('user_id', $request->user()->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($request, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $request->user()->id);
        })->first();

        if ($existing) {
            return response()->json(['message' => 'Заявка уже существует.'], 422);
        }

        $friendship = Friendship::create([
            'user_id' => $request->user()->id,
            'friend_id' => $user->id,
            'status' => 'pending',
        ]);

        $user->notify(new FriendRequestNotification($request->user()));

        return response()->json(['friendship' => $friendship], 201);
    }

    public function accept(Request $request, User $user): JsonResponse
    {
        $friendship = Friendship::where('user_id', $user->id)
            ->where('friend_id', $request->user()->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendship->update(['status' => 'accepted']);

        $user->notify(new FriendAcceptedNotification($request->user()));

        return response()->json(['friendship' => $friendship]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Friendship::where(function ($q) use ($request, $user) {
            $q->where('user_id', $request->user()->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($request, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $request->user()->id);
        })->delete();

        return response()->json(['message' => 'Удалено.']);
    }
}
