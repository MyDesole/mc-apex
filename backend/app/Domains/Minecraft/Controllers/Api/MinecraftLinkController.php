<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ник со стороны игрока: он заявляет его в профиле под своей сессией.
 *
 * Ник уникален на сайте, поэтому заявить чужой нельзя — а плагин пускает
 * только того, чей ник в игре совпадает с заявленным.
 */
class MinecraftLinkController extends Controller
{
    public function __construct(private readonly MinecraftLinkService $links)
    {
    }

    /** Состояние заявки для профиля. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'claimed' => (bool) $user->minecraft_username,
            'nickname' => $user->minecraft_username,
            'claimed_at' => $user->minecraft_linked_at?->toIso8601String(),
        ]);
    }

    /** Заявить ник. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $user = $this->links->claimNickname($request->user(), $data['nickname']);

        return response()->json([
            'claimed' => true,
            'nickname' => $user->minecraft_username,
            'claimed_at' => $user->minecraft_linked_at?->toIso8601String(),
        ]);
    }

    /** Снять заявку ника. */
    public function destroy(Request $request): JsonResponse
    {
        $this->links->releaseNickname($request->user());

        return response()->json([
            'claimed' => false,
            'nickname' => null,
        ]);
    }
}
