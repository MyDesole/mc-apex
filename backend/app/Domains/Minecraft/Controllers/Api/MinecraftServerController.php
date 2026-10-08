<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Эндпоинты для плагина майнкрафт-сервера.
 *
 * Доступ только с общим секретом сервера. Пароль сюда приходит по HTTPS,
 * сразу сверяется с хешем и нигде не сохраняется и не логируется.
 */
class MinecraftServerController extends Controller
{
    public function __construct(private readonly MinecraftLinkService $links)
    {
    }

    /** Игрок зашёл впервые — выдаём код привязки. */
    public function startLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
            'username' => ['required', 'string', 'max:32'],
        ]);

        $uuid = $this->links->normalizeUuid($data['uuid']);

        return response()->json(
            $this->links->startLink($uuid, $data['username'])
        );
    }

    /** Привязан ли игрок — плагин опрашивает, пока игрок вводит код. */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
        ]);

        return response()->json(
            $this->links->status($this->links->normalizeUuid($data['uuid']))
        );
    }

    /** Вход по паролю сайта. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $uuid = $this->links->normalizeUuid($data['uuid']);

        $result = $this->links->login($uuid, $data['password']);

        $payload = [
            'ok' => $result['ok'],
            'reason' => $result['reason'],
            'attempts_left' => $result['attempts_left'],
            'retry_after' => $result['retry_after'],
        ];

        if (! $result['ok']) {
            // 429 на блокировку, 401 на неверный пароль
            $status = $result['reason'] === 'throttled' ? 429 : 401;

            return response()->json($payload, $status);
        }

        $payload['player'] = $this->links->profilePayload($uuid);

        return response()->json($payload);
    }

    /** Данные игрока для показа в игре. */
    public function profile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
        ]);

        $profile = $this->links->profilePayload(
            $this->links->normalizeUuid($data['uuid'])
        );

        if (! $profile) {
            return response()->json(['linked' => false], 404);
        }

        return response()->json([
            'linked' => true,
            'player' => $profile,
        ]);
    }
}
