<?php

namespace App\Domains\Minecraft\Controllers\Api\Admin;

use App\Domains\Minecraft\Services\ServerRankService;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Ранги сервера в админке: выдать, снять, посмотреть. Только администратор. */
class ServerRankController extends Controller
{
    public function __construct(private readonly ServerRankService $ranks)
    {
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->ranks->state($user));
    }

    public function grant(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'rank' => ['required', 'string', 'in:' . implode(',', array_keys(ServerRankService::RANKS))],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        try {
            $state = $this->ranks->grant($user, $data['rank'], $data['days'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        ModerationLog::record($request->user(), 'user.rank_grant', $user->id, ['rank' => $data['rank'], 'days' => $data['days'] ?? null]);

        return response()->json($state);
    }

    public function revoke(Request $request, User $user, string $rank): JsonResponse
    {
        try {
            $state = $this->ranks->revoke($user, $rank);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        ModerationLog::record($request->user(), 'user.rank_revoke', $user->id, ['rank' => $rank]);

        return response()->json($state);
    }

    /** Команды для консоли сервера: создать группы, веса, префиксы. */
    public function setup(): JsonResponse
    {
        return response()->json(['commands' => $this->ranks->setupCommands()]);
    }
}
