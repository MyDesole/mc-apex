<?php

namespace App\Domains\Bridge\Controllers\Api;

use App\Domains\Bridge\Models\BridgeVideoUpload;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Requests\InitBridgeVideoRequest;
use App\Domains\Bridge\Requests\StoreBridgeVideoChunkRequest;
use App\Domains\Bridge\Services\BridgeVideoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Видео бридж-заявок: загрузка частями и отдача.
 *
 * Ролики лежат в закрытом хранилище: достать их можно только по подписанной
 * ссылке, которую выдаёт API. Прямых адресов к файлам нет.
 */
class BridgeVideoController extends Controller
{
    public function __construct(private readonly BridgeVideoService $videos)
    {
    }

    /** Начать загрузку: сколько частей ждать и какого размера. */
    public function init(InitBridgeVideoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->videos->init(
            user: $request->user(),
            fileName: $validated['file_name'],
            size: (int) $validated['size'],
            mime: $validated['mime'],
        );

        return response()->json($result, 201);
    }

    /** Принять одну часть. */
    public function chunk(StoreBridgeVideoChunkRequest $request, BridgeVideoUpload $upload): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->videos->storeChunk(
            user: $request->user(),
            upload: $upload,
            index: (int) $validated['index'],
            chunk: $request->file('chunk'),
        );

        return response()->json($result);
    }

    /** Собрать части в готовый файл. */
    public function complete(Request $request, BridgeVideoUpload $upload): JsonResponse
    {
        $done = $this->videos->complete($request->user(), $upload);

        return response()->json([
            'upload_id' => $done->uuid,
            'status' => $done->status,
            'size' => $done->size,
        ]);
    }

    /**
     * Отдать видео по подписанной ссылке.
     *
     * Ответ отдаётся файлом, поэтому работают переходы по таймлайну.
     */
    public function show(UserBridgeTechnique $submission): BinaryFileResponse
    {
        $path = $this->videos->pathFor($submission);

        if (! $path) {
            abort(404, 'Видео уже удалено.');
        }

        return response()->file($path, [
            'Content-Type' => $this->videos->mimeFor($submission),
        ]);
    }
}
