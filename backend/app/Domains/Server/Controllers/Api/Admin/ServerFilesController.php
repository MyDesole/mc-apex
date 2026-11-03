<?php

namespace App\Domains\Server\Controllers\Api\Admin;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Server\Services\GameServerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Мониторинг (TPS, CPU, плагины) и файловый менеджер сервера.
 * Роли — как у остальной панели: server_admin и admin (см. routes).
 * Удаления нет: файл уезжает в корзину на сервере.
 */
class ServerFilesController extends Controller
{
    public function __construct(private readonly GameServerService $server)
    {
    }

    private function audit(Request $request, string $action, array $details): void
    {
        ModerationLog::record($request->user(), $action, null, $details);
    }

    /** Ответ с ошибкой сервиса: 422 для понятных, 502 для сбоя канала. */
    private function guard(callable $fn, int $code = 422): JsonResponse|Response
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /** Кэш на 10 с: метрика шлёт команды в консоль, не дёргаем её чаще. */
    public function metrics(): JsonResponse|Response
    {
        return $this->guard(fn () => response()->json(
            Cache::remember('server.metrics', 10, fn () => $this->server->metrics())
        ), 502);
    }

    public function pluginsState(): JsonResponse|Response
    {
        return $this->guard(fn () => response()->json(
            Cache::remember('server.plugins_state', 30, fn () => $this->server->pluginsState())
        ), 502);
    }

    public function tree(Request $request): JsonResponse|Response
    {
        $path = (string) $request->query('path', '');

        return $this->guard(fn () => response()->json([
            'path' => $path,
            'entries' => $this->server->listDir($path),
        ]));
    }

    public function read(Request $request): JsonResponse|Response
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:300']]);

        return $this->guard(fn () => response()->json([
            'path' => $data['path'],
            'content' => $this->server->readText($data['path']),
        ]));
    }

    public function write(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:300'],
            'content' => ['nullable', 'string', 'max:1048576'],
        ]);

        return $this->guard(function () use ($request, $data) {
            $backup = $this->server->writeText($data['path'], (string) ($data['content'] ?? ''));
            $this->audit($request, 'server.file_save', ['path' => $data['path'], 'backup' => $backup]);

            return response()->json(['ok' => true, 'backup' => $backup]);
        });
    }

    public function create(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:300'],
            'kind' => ['required', 'in:file,dir'],
        ]);

        return $this->guard(function () use ($request, $data) {
            $this->server->createEntry($data['path'], $data['kind']);
            $this->audit($request, 'server.file_create', $data);

            return response()->json(['ok' => true]);
        });
    }

    public function rename(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:300'],
            'to' => ['required', 'string', 'max:300'],
        ]);

        return $this->guard(function () use ($request, $data) {
            $this->server->rename($data['from'], $data['to']);
            $this->audit($request, 'server.file_rename', $data);

            return response()->json(['ok' => true]);
        });
    }

    /** Подтверждение — флагом confirm: запрос без него не удаляет. */
    public function trash(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:300'],
            'confirm' => ['required', 'accepted'],
        ]);

        return $this->guard(function () use ($request, $data) {
            $this->server->trash($data['path']);
            $this->audit($request, 'server.file_trash', ['path' => $data['path']]);

            return response()->json(['ok' => true]);
        });
    }

    public function trashList(): JsonResponse|Response
    {
        return $this->guard(fn () => response()->json(['items' => $this->server->trashList()]), 502);
    }

    public function upload(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'dir' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->guard(function () use ($request, $data) {
            $rel = $this->server->uploadTo($data['file'], (string) ($data['dir'] ?? ''));
            $this->audit($request, 'server.file_upload', ['path' => $rel]);

            return response()->json(['ok' => true, 'path' => $rel]);
        });
    }

    public function download(Request $request): JsonResponse|Response
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:300']]);

        return $this->guard(function () use ($request, $data) {
            $body = $this->server->download($data['path']);
            $this->audit($request, 'server.file_download', ['path' => $data['path']]);

            return response($body, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($data['path'])) . '"',
            ]);
        });
    }
}
