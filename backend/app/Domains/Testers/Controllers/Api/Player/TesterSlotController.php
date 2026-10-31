<?php

namespace App\Domains\Testers\Controllers\Api\Player;

use App\Domains\Testers\Services\TesterScheduleService;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Notifications\TierTestRequestNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Свободные слоты тестеров и запись на них со стороны игрока.
 */
class TesterSlotController extends Controller
{
    public function __construct(private readonly TesterScheduleService $schedule)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 14);
        $days = min(30, max(1, $days));

        // Группируем по тестеру: игрок выбирает человека и время
        $grouped = $this->schedule->openSlots($days)
            ->groupBy('user_id')
            ->map(fn ($slots) => [
                'tester' => [
                    'id' => $slots->first()->tester->id,
                    'username' => $slots->first()->tester->username,
                    'avatar_url' => $slots->first()->tester->avatar_url,
                ],
                'slots' => $slots->map(fn ($slot) => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at->toIso8601String(),
                    'ends_at' => $slot->ends_at->toIso8601String(),
                    'note' => $slot->note,
                ])->values(),
            ])
            ->values();

        return response()->json(['testers' => $grouped]);
    }

    /** Записать свою заявку на конкретный слот. */
    public function book(Request $request, TierTest $tierTest): JsonResponse
    {
        abort_if($tierTest->user_id !== $request->user()->id, 403, 'Это не ваша заявка.');

        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $slot = $this->schedule->book($tierTest, (int) $data['slot_id']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Тестер должен узнать, что к нему записались
        $slot->tester?->notify(new TierTestRequestNotification($tierTest->fresh()));

        return response()->json([
            'slot' => $slot,
            'scheduled_at' => $tierTest->fresh()->scheduled_at?->toIso8601String(),
        ]);
    }
}
