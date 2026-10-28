<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\PetArenaService;
use App\Domains\Players\Services\PetExpeditionService;
use App\Domains\Players\Services\PetHubService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * «Мои питомцы»: экспедиции, арена, трюки, питомцедекс, топ.
 */
class PetHubController extends Controller
{
    public function __construct(
        private readonly PetHubService $hub,
        private readonly PetExpeditionService $expeditions,
        private readonly PetArenaService $arena,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->hub->overview($request->user()));
    }

    public function trick(Request $request, int $row): JsonResponse
    {
        $data = $request->validate(['trick' => ['nullable', 'string', 'max:24']]);

        return $this->run(fn () => $this->hub->setTrick($request->user(), $row, $data['trick'] ?? null));
    }

    public function expedition(Request $request, int $row): JsonResponse
    {
        $data = $request->validate(['zone' => ['required', 'string', 'max:24']]);

        return $this->run(fn () => $this->expeditions->start($request->user(), $row, $data['zone']), 201);
    }

    public function claimExpedition(Request $request, int $expedition): JsonResponse
    {
        return $this->run(fn () => $this->expeditions->claim($request->user(), $expedition));
    }

    public function opponents(Request $request, int $row): JsonResponse
    {
        return $this->run(fn () => ['opponents' => $this->arena->opponents($request->user(), $row)]);
    }

    public function battle(Request $request, int $row): JsonResponse
    {
        $data = $request->validate(['opponent' => ['required', 'integer']]);

        return $this->run(fn () => $this->arena->start($request->user(), $row, (int) $data['opponent']), 201);
    }

    public function showBattle(Request $request, int $battle): JsonResponse
    {
        return $this->run(fn () => $this->arena->show($request->user(), $battle));
    }

    public function move(Request $request, int $battle): JsonResponse
    {
        $data = $request->validate(['move' => ['required', 'string']]);

        return $this->run(fn () => $this->arena->move($request->user(), $battle, $data['move']));
    }

    public function forfeit(Request $request, int $battle): JsonResponse
    {
        return $this->run(fn () => $this->arena->forfeit($request->user(), $battle));
    }

    public function dex(Request $request): JsonResponse
    {
        return response()->json($this->hub->dex($request->user()));
    }

    public function claimDex(Request $request, string $milestone): JsonResponse
    {
        return $this->run(fn () => $this->hub->claimMilestone($request->user(), $milestone));
    }

    public function top(Request $request): JsonResponse
    {
        $by = in_array($request->query('by'), ['level', 'rating', 'rare'], true) ? $request->query('by') : 'level';

        return response()->json(['by' => $by, 'pets' => $this->hub->top($by)]);
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
