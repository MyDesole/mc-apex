<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\DungeonService;
use App\Domains\Shop\Exceptions\NotEnoughCoins;
use App\Domains\Players\Services\PetGearService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Подземелья и экипировка питомцев.
 *
 * Бой пошаговый: `advance` открывает зал (начинает бой), `move` разыгрывает
 * раунд с выбранными приёмами. Всё состояние лежит в базе, поэтому бой
 * продолжается с любого устройства.
 */
class DungeonController extends Controller
{
    public function __construct(
        private readonly DungeonService $dungeons,
        private readonly PetGearService $gear,
        private readonly \App\Domains\Players\Services\DungeonCraftService $craft,
    ) {
    }

    /** Склад материалов и правила крафта. */
    public function craftIndex(Request $request): JsonResponse
    {
        return response()->json([
            'materials' => $this->craft->materials($request->user()),
            'catalog' => \App\Domains\Players\Support\Dungeons::MATERIALS,
            'cost' => \App\Domains\Players\Services\DungeonCraftService::COST,
            'recipes' => $this->craft->recipes(),
        ]);
    }

    /** Ковка на ядре босса: выбранный слот, редкость не ниже эпической. */
    public function forge(Request $request): JsonResponse
    {
        $data = $request->validate(['core' => ['required', 'string', 'max:32'], 'slot' => ['required', 'string', 'max:12']]);

        return $this->craftRun(fn () => $this->craft->forge($request->user(), $data['core'], $data['slot']));
    }

    /** Усиление: два одинаковых предмета и монеты. */
    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate(['first' => ['required', 'integer', 'min:1'], 'second' => ['required', 'integer', 'min:1']]);

        return $this->craftRun(fn () => $this->craft->upgrade($request->user(), (int) $data['first'], (int) $data['second']));
    }

    /** Переплавка предмета в материалы. */
    public function dismantle(Request $request): JsonResponse
    {
        $data = $request->validate(['item' => ['required', 'integer', 'min:1']]);

        return $this->craftRun(fn () => $this->craft->dismantle($request->user(), (int) $data['item']));
    }

    private function craftRun(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation(), 201);
        } catch (\App\Domains\Shop\Exceptions\NotEnoughCoins $e) {
            return response()->json(['message' => $e->getMessage(), 'missing' => $e->missing], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Скрафтить предмет из трёх материалов. */
    public function craft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material' => ['required', 'string', 'max:24'],
        ]);

        try {
            $result = $this->craft->craft($request->user(), (string) $data['material']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 201);
    }

    /** Сводка: подземелья, отряд, активный поход, экипировка. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->dungeons->overview($request->user()));
    }

    /** Неизменные данные: описания, свойства врагов, справочник боя. Кэшируется. */
    public function reference(): JsonResponse
    {
        return response()->json($this->dungeons->reference())
            ->header('Cache-Control', 'private, max-age=600');
    }

    /** Начать поход: подземелье и отряд 2–3 питомцев. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dungeon' => ['required', 'string', 'max:32'],
            'squad' => ['required', 'array', 'min:2', 'max:3'],
            'squad.*' => ['integer', 'min:1'],
        ]);

        return $this->run(fn () => $this->dungeons->start(
            $request->user(),
            (string) $data['dungeon'],
            $data['squad'],
        ), 201);
    }

    /** Войти в зал: начать бой. */
    public function advance(Request $request, int $run): JsonResponse
    {
        return $this->run(fn () => $this->dungeons->enterRoom($request->user(), $run));
    }

    /** Раунд боя: приёмы каждому живому питомцу. */
    public function move(Request $request, int $run): JsonResponse
    {
        $data = $request->validate([
            'moves' => ['nullable', 'array'],
            'moves.*' => ['string', 'in:attack,skill,defend,ultimate'],
        ]);

        return $this->run(fn () => $this->dungeons->move(
            $request->user(),
            $run,
            $data['moves'] ?? [],
        ) + ['balance' => (int) $request->user()->fresh()->apex_coins]);
    }

    /** Купить ещё один поход на сегодня. */
    public function buyRun(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->dungeons->buyExtraRun($request->user()));
    }

    /** Отступить, сохранив добычу. */
    public function retreat(Request $request, int $run): JsonResponse
    {
        return $this->run(fn () => $this->dungeons->retreat($request->user(), $run)
            + ['balance' => (int) $request->user()->fresh()->apex_coins]);
    }

    /** Вся экипировка игрока. */
    public function gear(Request $request): JsonResponse
    {
        return response()->json(['gear' => $this->gear->bag($request->user())]);
    }

    /** Надеть предмет на питомца. */
    public function equip(Request $request, int $pet): JsonResponse
    {
        $data = $request->validate(['gear' => ['required', 'integer', 'min:1']]);

        return $this->run(fn () => $this->gear->equip($request->user(), $pet, (int) $data['gear']));
    }

    /** Снять предмет со слота. */
    public function unequip(Request $request, int $pet, string $slot): JsonResponse
    {
        return $this->run(fn () => $this->gear->unequip($request->user(), $pet, $slot));
    }

    private function run(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json($operation(), $status);
        } catch (NotEnoughCoins $e) {
            // Клиент предложит, где заработать
            return response()->json(['message' => $e->getMessage(), 'missing' => $e->missing, 'earn' => '/miner'], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
