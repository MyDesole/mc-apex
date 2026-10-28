<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Domains\Shop\Services\PetAuctionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Аукцион питомцев: лоты, ставки, выставление и снятие.
 */
class PetAuctionController extends Controller
{
    public function __construct(private readonly PetAuctionService $auctions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'lots' => $this->auctions->list($request->user('sanctum')),
            'durations' => PetAuctionService::DURATIONS,
            'min_start' => PetAuctionService::MIN_START,
        ]);
    }

    public function show(Request $request, int $auction): JsonResponse
    {
        return response()->json($this->auctions->show($auction, $request->user('sanctum')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_id' => ['required', 'integer'],
            'start_price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'hours' => ['required', 'integer'],
        ]);

        return $this->run(fn () => $this->auctions->create(
            $request->user(),
            (int) $data['inventory_id'],
            (int) $data['start_price'],
            (int) $data['hours'],
        ), 201);
    }

    public function bid(Request $request, int $auction): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:100000000']]);

        return $this->run(fn () => $this->auctions->bid($request->user(), $auction, (int) $data['amount']));
    }

    public function cancel(Request $request, int $auction): JsonResponse
    {
        return $this->run(function () use ($request, $auction) {
            $this->auctions->cancel($request->user(), $auction);

            return ['ok' => true];
        });
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
