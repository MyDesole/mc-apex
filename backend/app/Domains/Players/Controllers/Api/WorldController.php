<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\WorldService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Карта-мир: сводка для значков на постройках. */
class WorldController extends Controller
{
    public function show(Request $request, WorldService $world): JsonResponse
    {
        return response()->json($world->state($request->user()));
    }
}
