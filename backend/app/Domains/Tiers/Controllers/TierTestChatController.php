<?php

namespace App\Domains\Tiers\Controllers;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Services\TierTestChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Чат заявки на тир-тест: один набор ручек и для тестируемого, и для тестера.
 * Кто есть кто, решает TierTestChatService по заявке.
 */
class TierTestChatController extends Controller
{
    public function __construct(private readonly TierTestChatService $chat)
    {
    }

    /** Мои активные чаты (для плавающей кнопки и бейджей непрочитанного). */
    public function active(Request $request): JsonResponse
    {
        return response()->json(['chats' => $this->chat->activeFor($request->user())]);
    }

    public function show(Request $request, TierTest $tierTest): JsonResponse
    {
        return response()->json(
            $this->chat->thread($tierTest, $request->user(), (int) $request->query('after', 0))
        );
    }

    public function send(Request $request, TierTest $tierTest): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:' . TierTestChatService::MAX_LENGTH * 2]]);

        return response()->json(
            ['message' => $this->chat->send($tierTest, $request->user(), $data['body'])],
            201,
        );
    }
}
