<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftSkinService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Concerns\ResolvesFromUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Скин игрока для 3D-модели в профиле и карточки игрока, а также
 * загрузка своего скина вместо скина из Minecraft.
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
            'X-Content-Type-Options' => 'nosniff',
            'X-Skin-Model' => $skin['model'],
            'Access-Control-Expose-Headers' => 'X-Skin-Model',
        ]);
    }

    /** Загрузить свой скин: PNG 64×64 (или 64×32) и модель рук. */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skin' => ['required', 'file', 'mimetypes:image/png', 'max:64'],
            'model' => ['required', Rule::in(['classic', 'slim'])],
        ], [
            'skin.mimetypes' => 'Скин должен быть PNG-файлом.',
            'skin.max' => 'Файл скина больше 64 КБ — так не бывает.',
        ]);

        try {
            $this->skins->saveCustom($request->user(), $data['skin']->get(), $data['model']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['skin_version' => $request->user()->skin_version]);
    }

    /** Вернуть скин из Minecraft. */
    public function destroy(Request $request): JsonResponse
    {
        $this->skins->removeCustom($request->user());

        return response()->json(['skin_version' => null]);
    }
}
