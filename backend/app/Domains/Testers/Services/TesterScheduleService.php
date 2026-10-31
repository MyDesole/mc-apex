<?php

namespace App\Domains\Testers\Services;

use App\Domains\Testers\Models\TesterProfile;
use App\Domains\Testers\Models\TesterSlot;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Календарь тестера: свободные слоты и запись на них.
 *
 * Правила:
 *  - слот нельзя открыть в прошлом;
 *  - слоты одного тестера не пересекаются;
 *  - длительность от 30 минут до 4 часов;
 *  - записаться можно только в открытый будущий слот;
 *  - заблокированный тестер не открывает новые слоты.
 */
class TesterScheduleService
{
    public const MIN_MINUTES = 30;
    public const MAX_MINUTES = 240;
    public const MAX_AHEAD_DAYS = 30;

    /** Профиль тестера создаём лениво: у большинства его ещё нет. */
    public function profile(User $tester): TesterProfile
    {
        return TesterProfile::firstOrCreate(['user_id' => $tester->id]);
    }

    public function isBlocked(User $tester): bool
    {
        $profile = TesterProfile::where('user_id', $tester->id)->first();

        return $profile?->blocked_until !== null && $profile->blocked_until->isFuture();
    }

    /** Слоты тестера за окно; по умолчанию — с сегодня на две недели вперёд. */
    public function window(User $tester, ?string $from = null, ?string $to = null): Collection
    {
        $from = $from ? \Illuminate\Support\Carbon::parse($from) : now()->startOfDay();
        $to = $to ? \Illuminate\Support\Carbon::parse($to) : now()->addDays(14)->endOfDay();

        return TesterSlot::with('tierTest:id,user_id,status,result_tier,scheduled_at')
            ->where('user_id', $tester->id)
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();
    }

    /** Открыть слот. */
    public function open(User $tester, string $startsAt, string $endsAt, ?string $note = null): TesterSlot
    {
        if ($this->isBlocked($tester)) {
            throw new RuntimeException('Доступ к календарю закрыт администратором.');
        }

        $start = \Illuminate\Support\Carbon::parse($startsAt);
        $end = \Illuminate\Support\Carbon::parse($endsAt);

        if ($start->isPast()) {
            throw new RuntimeException('Нельзя открыть слот в прошлом.');
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw new RuntimeException('Конец слота должен быть позже начала.');
        }

        $minutes = $start->diffInMinutes($end);

        if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
            throw new RuntimeException('Слот должен длиться от 30 минут до 4 часов.');
        }

        if ($start->greaterThan(now()->addDays(self::MAX_AHEAD_DAYS))) {
            throw new RuntimeException('Открывать слоты можно не дальше чем на месяц вперёд.');
        }

        $overlaps = TesterSlot::where('user_id', $tester->id)
            ->where('status', '!=', TesterSlot::CLOSED)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($overlaps) {
            throw new RuntimeException('На это время уже есть слот.');
        }

        return TesterSlot::create([
            'user_id' => $tester->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => TesterSlot::OPEN,
            'note' => $note,
        ]);
    }

    /** Снять слот: занятый снять нельзя. */
    public function close(User $tester, int $slotId): void
    {
        $slot = TesterSlot::where('user_id', $tester->id)->findOrFail($slotId);

        if ($slot->status === TesterSlot::BOOKED) {
            throw new RuntimeException('Слот занят заявкой — сначала отмените тест.');
        }

        $slot->update(['status' => TesterSlot::CLOSED]);
    }

    /** Свободные слоты всех тестеров — для записи игроком. */
    public function openSlots(int $days = 14): Collection
    {
        $until = now()->addDays($days)->endOfDay();

        return TesterSlot::query()
            ->with('tester:id,username,avatar')
            ->where('status', TesterSlot::OPEN)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', $until)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (TesterSlot $slot) => $slot->tester !== null && ! $this->isBlocked($slot->tester))
            ->values();
    }

    /** Записать заявку на слот. */
    public function book(TierTest $test, int $slotId): TesterSlot
    {
        if ($test->status !== 'pending') {
            throw new RuntimeException('Записаться можно только на ожидающую заявку.');
        }

        $slot = TesterSlot::find($slotId);

        if (! $slot) {
            throw new RuntimeException('Слот не найден.');
        }

        if ($slot->status !== TesterSlot::OPEN) {
            throw new RuntimeException('Это время уже занято.');
        }

        if ($slot->starts_at->isPast()) {
            throw new RuntimeException('Это время уже прошло.');
        }

        if ($this->isBlocked($slot->tester)) {
            throw new RuntimeException('Тестер сейчас не принимает записи.');
        }

        $slot->update([
            'status' => TesterSlot::BOOKED,
            'tier_test_id' => $test->id,
        ]);

        $test->update(['scheduled_at' => $slot->starts_at]);

        return $slot->fresh(['tester:id,username']);
    }

    /**
     * Освободить слот при отмене заявки.
     *
     * Если время ещё не прошло — слот снова открыт, иначе закрыт: прошлое
     * время не должно снова попасть в запись.
     */
    public function release(TierTest $test): void
    {
        $slot = TesterSlot::where('tier_test_id', $test->id)->first();

        if (! $slot) {
            return;
        }

        $slot->update([
            'status' => $slot->starts_at->isFuture() ? TesterSlot::OPEN : TesterSlot::CLOSED,
            'tier_test_id' => null,
        ]);
    }

    /** Календарь всех тестеров — админу. */
    public function calendar(?string $from = null, ?string $to = null): Collection
    {
        $from = $from ? \Illuminate\Support\Carbon::parse($from) : now()->startOfDay();
        $to = $to ? \Illuminate\Support\Carbon::parse($to) : now()->addDays(7)->endOfDay();

        return TesterSlot::query()
            ->with([
                'tester:id,username,avatar',
                'tierTest:id,user_id,status,result_tier',
                'tierTest.user:id,username',
            ])
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();
    }

    /** Заблокировать или разблокировать календарь тестера. */
    public function block(User $tester, ?string $until, ?string $reason): TesterProfile
    {
        $profile = $this->profile($tester);

        $profile->update([
            'blocked_until' => $until ? \Illuminate\Support\Carbon::parse($until) : null,
            'blocked_reason' => $until ? $reason : null,
        ]);

        return $profile;
    }
}
