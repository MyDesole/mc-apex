<?php

namespace App\Domains\Friends\Controllers;

use App\Domains\Friends\Resources\FriendshipResource;
use App\Domains\Players\Resources\UserCardResource;
use App\Domains\Users\Models\User;
use App\Domains\Friends\Services\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Друзья. Контроллер тонкий: списки и правила — в FriendService,
 * формат карточки игрока — в UserCardResource.
 */
class FriendController extends Controller
{
    public function __construct(
        private readonly FriendService $friends,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $overview = $this->friends->overview($me);

        return response()->json([
            'friends' => UserCardResource::collection($overview['friends']),
            'incoming_requests' => FriendshipResource::collection($overview['incoming']),
            'outgoing_requests' => FriendshipResource::collection($overview['outgoing']),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $friendship = $this->friends->request($request->user(), $user);

        return response()->json(['friendship' => $friendship], 201);
    }

    public function accept(Request $request, User $user): JsonResponse
    {
        $friendship = $this->friends->accept($request->user(), $user);

        return response()->json(['friendship' => $friendship]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->friends->remove($request->user(), $user);

        return response()->json(['message' => 'Удалено.']);
    }
}
