<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\InventoryTradeService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Продать предмет из инвентаря магазину или подарить другу.
 */
class InventoryTradeController extends Controller
{
    public function __construct(private readonly InventoryTradeService $trade)
    {
    }

    public function sell(Request $request, UserInventory $row): JsonResponse
    {
        abort_unless($row->user_id === $request->user()->id, 404);

        return $this->run(fn () => $this->trade->sell($request->user(), $row->load('item')));
    }

    public function gift(Request $request, UserInventory $row): JsonResponse
    {
        abort_unless($row->user_id === $request->user()->id, 404);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        return $this->run(fn () => $this->trade->gift(
            $request->user(),
            $row->load('item'),
            User::findOrFail($data['user_id']),
        ));
    }

    private function run(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
