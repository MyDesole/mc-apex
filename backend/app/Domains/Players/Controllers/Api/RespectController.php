<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\RespectService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Респекты, гости профиля и топ самых уважаемых недели.
 */
class RespectController extends Controller
{
    public function __construct(private readonly RespectService $respects)
    {
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json($this->respects->summary($user, $request->user('sanctum')));
    }

    public function give(Request $request, User $user): JsonResponse
    {
        return response()->json($this->respects->give($user, $request->user()));
    }

    public function revoke(Request $request, User $user): JsonResponse
    {
        return response()->json($this->respects->revoke($user, $request->user()));
    }

    public function visit(Request $request, User $user): JsonResponse
    {
        $this->respects->visit($user, $request->user());

        return response()->json(['ok' => true]);
    }

    /** Свои гости — видит только хозяин профиля. */
    public function guests(Request $request): JsonResponse
    {
        return response()->json($this->respects->guests($request->user()));
    }

    public function top(): JsonResponse
    {
        return response()->json(['players' => $this->respects->leaderboard()]);
    }
}
