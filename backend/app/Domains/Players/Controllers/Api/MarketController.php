<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\MarketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Рынок игроков: материалы и снаряжение подземелий. */
class MarketController extends Controller
{
    public function __construct(private readonly MarketService $market)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $kind = (string) $request->query('kind', 'all');

        return $this->run(fn () => $this->market->state($request->user(), $kind));
    }

    public function sellMaterial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material' => ['required', 'string', 'max:32'],
            'qty' => ['required', 'integer', 'min:1', 'max:' . MarketService::MAX_QTY],
            'price' => ['required', 'integer', 'min:1'],
        ]);

        return $this->run(fn () => $this->market->sellMaterial($request->user(), $data['material'], (int) $data['qty'], (int) $data['price']), 201);
    }

    public function sellGear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:1'],
        ]);

        return $this->run(fn () => $this->market->sellGear($request->user(), (int) $data['item'], (int) $data['price']), 201);
    }

    public function buy(Request $request, int $listing): JsonResponse
    {
        return $this->run(fn () => $this->market->buy($request->user(), $listing));
    }

    public function cancel(Request $request, int $listing): JsonResponse
    {
        return $this->run(fn () => $this->market->cancel($request->user(), $listing));
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
