<?php

namespace App\Domains\Server\Controllers\Api\Admin;

use App\Domains\Server\Services\GameServerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Панель игрового сервера: статус, консоль, питание, обновления.
 * Доступ — только «Администратор сайта» и админ.
 */
class ServerController extends Controller
{
    public function __construct(private readonly GameServerService $server)
    {
    }

    public function status(): JsonResponse
    {
        try {
            return response()->json($this->server->status());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /** Точное число игроков (через list в консоль сервера). */
    public function players(): JsonResponse
    {
        try {
            return response()->json($this->server->players());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /** Плагины на сервере: имя и размер jar. */
    public function plugins(): JsonResponse
    {
        try {
            return response()->json(['plugins' => $this->server->plugins()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function console(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'log' => $this->server->console((int) $request->query('lines', 150)),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /** start | stop | restart */
    public function power(string $action): JsonResponse
    {
        try {
            $result = match ($action) {
                'start' => $this->server->start(),
                'stop' => $this->server->stop(),
                'restart' => $this->server->restart(),
                default => throw new RuntimeException('Неизвестное действие.'),
            };

            return response()->json(['ok' => true, 'result' => $result]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function command(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', 'max:300'],
        ]);

        try {
            return response()->json(['ok' => true, 'result' => $this->server->command((string) $data['command'])]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function startCommand(): JsonResponse
    {
        try {
            return response()->json(['content' => $this->server->startCommand()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function saveStartCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $this->server->saveStartCommand((string) $data['content']);

            return response()->json(['ok' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:65536'],
            'target' => ['nullable', 'string', 'in:plugins'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'uploaded' => $this->server->upload($data['file'], (string) ($data['target'] ?? '')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
