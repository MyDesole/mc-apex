<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\PersonalFarmService;
use App\Domains\Players\Services\PetBreedingService;
use App\Domains\Players\Services\PetFarmService;
use App\Domains\Shop\Exceptions\NotEnoughCoins;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Фермы питомцев: Общая (бронь и выкуп) и Личная (грядки, Кладовка,
 * скрещивание). Всё решает сервер, клиент только показывает.
 */
class PetFarmController extends Controller
{
    public function __construct(
        private readonly PetFarmService $farm,
        private readonly PersonalFarmService $personal,
        private readonly PetBreedingService $breeding,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->farm->state($request->user()));
    }

    public function reserve(Request $request, int $pet): JsonResponse
    {
        return $this->run(fn () => $this->farm->reserve($request->user(), $pet));
    }

    public function buy(Request $request, int $pet): JsonResponse
    {
        return $this->run(fn () => $this->farm->buy($request->user(), $pet));
    }

    public function personal(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->personal->state($request->user()));
    }

    public function unlock(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->personal->unlock($request->user()), 201);
    }

    public function plant(Request $request): JsonResponse
    {
        $data = $request->validate(['species' => ['required', 'string', 'max:24']]);

        return $this->run(fn () => $this->personal->plant($request->user(), $data['species']), 201);
    }

    public function harvest(Request $request, int $plot): JsonResponse
    {
        return $this->run(fn () => $this->personal->harvest($request->user(), $plot));
    }

    public function speedUpPlot(Request $request, int $plot): JsonResponse
    {
        return $this->run(fn () => $this->personal->speedUp($request->user(), $plot));
    }

    public function speedUpMating(Request $request, int $mating): JsonResponse
    {
        return $this->run(fn () => $this->breeding->speedUp($request->user(), $mating));
    }

    public function mate(Request $request): JsonResponse
    {
        $data = $request->validate($this->pairRules());

        return $this->run(fn () => $this->breeding->mate($request->user(), (int) $data['parent1'], (int) $data['parent2']), 201);
    }

    public function claimMating(Request $request, int $mating): JsonResponse
    {
        return $this->run(fn () => $this->breeding->claim($request->user(), $mating));
    }

    public function cross(Request $request): JsonResponse
    {
        $data = $request->validate($this->pairRules());

        return $this->run(fn () => $this->breeding->cross($request->user(), (int) $data['parent1'], (int) $data['parent2']), 201);
    }

    private function pairRules(): array
    {
        return [
            'parent1' => ['required', 'integer', 'min:1'],
            'parent2' => ['required', 'integer', 'min:1'],
        ];
    }

    private function run(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json($operation(), $status);
        } catch (NotEnoughCoins $e) {
            return response()->json(['message' => $e->getMessage(), 'missing' => $e->missing, 'earn' => '/miner'], 422);
        } catch (RuntimeException $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
