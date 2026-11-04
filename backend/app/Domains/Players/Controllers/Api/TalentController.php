<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\PetTalentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Академия: таланты питомцев. */
class TalentController extends Controller
{
    public function __construct(private readonly PetTalentService $talents)
    {
    }

    public function roster(Request $request): JsonResponse
    {
        return response()->json(['pets' => $this->talents->roster($request->user())]);
    }

    public function show(Request $request, int $pet): JsonResponse
    {
        return $this->run(fn () => $this->talents->tree($request->user(), $pet));
    }

    public function learn(Request $request, int $pet): JsonResponse
    {
        $data = $request->validate(['talent' => ['required', 'string', 'max:24']]);

        return $this->run(fn () => $this->talents->learn($request->user(), $pet, $data['talent']));
    }

    public function respec(Request $request, int $pet): JsonResponse
    {
        return $this->run(fn () => $this->talents->respec($request->user(), $pet));
    }

    private function run(callable $call): JsonResponse
    {
        try {
            return response()->json($call());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
