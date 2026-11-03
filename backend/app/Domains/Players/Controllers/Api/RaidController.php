<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\LavkaService;
use App\Domains\Players\Services\RaidService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Рейд «Разлом» и Лавка (Рубли и Осколки Разлома). */
class RaidController extends Controller
{
    public function __construct(private readonly RaidService $raids, private readonly LavkaService $lavka)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->raids->state($request->user()));
    }

    public function show(Request $request, int $raid): JsonResponse
    {
        return $this->run(fn () => $this->raids->show($request->user(), $raid));
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate($this->squadRules());

        return $this->run(fn () => $this->raids->create($request->user(), $data['squad']), 201);
    }

    public function join(Request $request, int $raid): JsonResponse
    {
        $data = $request->validate($this->squadRules());

        return $this->run(fn () => $this->raids->join($request->user(), $raid, $data['squad']));
    }

    public function leave(Request $request, int $raid): JsonResponse
    {
        return $this->run(fn () => $this->raids->leave($request->user(), $raid));
    }

    public function start(Request $request, int $raid): JsonResponse
    {
        return $this->run(fn () => $this->raids->start($request->user(), $raid));
    }

    public function moves(Request $request, int $raid): JsonResponse
    {
        $data = $request->validate(['moves' => ['required', 'array', 'min:1', 'max:3']]);

        return $this->run(fn () => $this->raids->submit($request->user(), $raid, $data['moves']));
    }

    public function lavka(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->lavka->state($request->user()));
    }

    public function buyPrivilege(Request $request, string $key): JsonResponse
    {
        return $this->run(fn () => $this->lavka->buyPrivilege($request->user(), $key), 201);
    }

    public function buyShards(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['pet' => ['nullable', 'integer', 'min:1']]);

        return $this->run(fn () => $this->lavka->buyWithShards($request->user(), $key, isset($data['pet']) ? (int) $data['pet'] : null), 201);
    }

    private function squadRules(): array
    {
        return ['squad' => ['required', 'array', 'size:3'], 'squad.*' => ['integer', 'min:1']];
    }

    private function run(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json($operation(), $status);
        } catch (RuntimeException $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
