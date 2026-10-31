<?php

namespace App\Domains\Testers\Controllers\Api\Tester;

use App\Domains\Testers\Services\TesterEarningsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Заработок и запросы на выплату.
 */
class PayoutController extends Controller
{
    public function __construct(private readonly TesterEarningsService $earnings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'summary' => $this->earnings->summary($request->user()),
            'history' => $this->earnings->history($request->user()),
            'payouts' => $this->earnings->payouts($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $payout = $this->earnings->requestPayout($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['payout' => $payout], 201);
    }
}
