<?php

namespace App\Domains\Testers\Controllers\Api\Tester;

use App\Domains\Testers\Services\TesterScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Слоты календаря тестера: открыть, посмотреть, снять.
 */
class SlotController extends Controller
{
    public function __construct(private readonly TesterScheduleService $schedule)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'slots' => $this->schedule->window(
                $request->user(),
                $data['from'] ?? null,
                $data['to'] ?? null,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $slot = $this->schedule->open(
                $request->user(),
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['slot' => $slot], 201);
    }

    public function destroy(Request $request, int $slot): JsonResponse
    {
        try {
            $this->schedule->close($request->user(), $slot);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
