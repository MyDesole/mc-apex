<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\PetService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Питомец профиля: состояние, действия гостей и имя.
 */
class PetController extends Controller
{
    public function __construct(private readonly PetService $pets)
    {
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json($this->pets->stats(
            $user,
            $request->user('sanctum'),
            $request->filled('pet') ? $request->integer('pet') : null,
        ));
    }

    public function interact(Request $request, User $user, string $action): JsonResponse
    {
        return response()->json($this->pets->interact($user, $request->user(), $action));
    }

    public function rename(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['present', 'nullable', 'string', 'max:20'],
            'inventory_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'name' => $this->pets->rename($request->user(), $data['name'], $data['inventory_id'] ?? null),
        ]);
    }
}
