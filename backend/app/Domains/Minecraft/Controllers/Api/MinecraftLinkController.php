<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Привязка майнкрафта со стороны игрока: он вводит код в профиле на сайте.
 */
class MinecraftLinkController extends Controller
{
    public function __construct(private readonly MinecraftLinkService $links)
    {
    }

    /** Состояние привязки для профиля. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'linked' => (bool) $user->minecraft_uuid,
            'username' => $user->minecraft_username,
            'linked_at' => $user->minecraft_linked_at?->toIso8601String(),
        ]);
    }

    /** Ввести код, полученный в игре. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        $user = $this->links->link($request->user(), $data['code']);

        return response()->json([
            'linked' => true,
            'username' => $user->minecraft_username,
            'linked_at' => $user->minecraft_linked_at?->toIso8601String(),
        ]);
    }

    /** Отвязать игрока. */
    public function destroy(Request $request): JsonResponse
    {
        $this->links->unlink($request->user());

        return response()->json([
            'linked' => false,
            'username' => null,
        ]);
    }
}
