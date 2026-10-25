<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftSkinService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Concerns\ResolvesFromUrl;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Скин игрока для 3D-модели в профиле и карточки игрока.
 *
 * Модель рук (classic/slim) отдаётся заголовком X-Skin-Model: картинке
 * не нужен отдельный запрос за метаданными.
 */
class MinecraftSkinController extends Controller
{
    use ResolvesFromUrl;

    public function __construct(
        private readonly MinecraftSkinService $skins,
    ) {
    }

    public function show(string $user): BinaryFileResponse|JsonResponse
    {
        $player = $this->resolveFromUrl(User::class, $user, 'username', 'Игрок не найден.');

        $skin = $this->skins->skinFor($player);

        if (! $skin) {
            return response()->json(['message' => 'Скин не найден.'], 404);
        }

        return response()->file($skin['path'], [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
            'X-Skin-Model' => $skin['model'],
            'Access-Control-Expose-Headers' => 'X-Skin-Model',
        ]);
    }
}
