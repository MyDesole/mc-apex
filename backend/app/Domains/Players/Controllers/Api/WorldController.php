<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\WorldService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Карта-мир: сводка для значков на постройках и вход в Цитадель. */
class WorldController extends Controller
{
    public function show(Request $request, WorldService $world): JsonResponse
    {
        return response()->json($world->state($request->user()));
    }

    /** Единоразовая покупка входа во вторую локацию. */
    public function unlockCitadel(Request $request, WorldService $world): JsonResponse
    {
        try {
            $world->unlockCitadel($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($world->state($request->user()->fresh()));
    }
}
