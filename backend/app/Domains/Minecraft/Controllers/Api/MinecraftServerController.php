<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Эндпоинты для плагина майнкрафт-сервера.
 *
 * Доступ только с общим секретом сервера. Пароль приходит по HTTPS, сразу
 * сверяется с хешем и нигде не сохраняется и не логируется.
 */
class MinecraftServerController extends Controller
{
    public function __construct(private readonly MinecraftLinkService $links)
    {
    }

    /**
     * Заявлен ли ник и на какой аккаунт.
     *
     * По этому ответу плагин решает: просить пароль или отправить игрока
     * заявить ник в профиле.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($this->links->resolveNickname($data['nickname']));
    }

    /** Вход по нику и паролю сайта. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
            'uuid' => ['nullable', 'string', 'max:36'],
        ]);

        $uuid = isset($data['uuid']) && $data['uuid'] !== ''
                ? $this->links->normalizeUuid($data['uuid'])
                : null;

        $result = $this->links->login($data['nickname'], $data['password'], $uuid);

        $payload = [
            'ok' => $result['ok'],
            'reason' => $result['reason'],
            'attempts_left' => $result['attempts_left'],
            'retry_after' => $result['retry_after'],
        ];

        if (! $result['ok']) {
            // 429 на блокировку, 401 на неверный пароль
            return response()->json($payload, $result['reason'] === 'throttled' ? 429 : 401);
        }

        $payload['player'] = $this->links->profilePayload($result['user']);

        return response()->json($payload);
    }

    /** Данные игрока для показа в игре. */
    public function profile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $profile = $this->links->profileByNickname($data['nickname']);

        if (! $profile) {
            return response()->json(['claimed' => false], 404);
        }

        return response()->json([
            'claimed' => true,
            'player' => $profile,
        ]);
    }

    /**
     * На какой аккаунт заявлен ник.
     *
     * Нужно админу, когда игрок не может зайти: в offline-режиме доказать
     * принадлежность ника нельзя, поэтому решает админ.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $found = $this->links->lookupByNickname($data['nickname']);

        return response()->json($found ?? [
            'claimed' => false,
            'nickname' => $data['nickname'],
        ]);
    }

    /** Освободить ник от аккаунта. */
    public function unlink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $done = $this->links->adminRelease($data['nickname']);

        return response()->json([
            'ok' => $done,
            'nickname' => $data['nickname'],
        ]);
    }
}
