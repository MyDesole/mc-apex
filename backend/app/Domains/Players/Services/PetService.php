<?php

namespace App\Domains\Players\Services;

use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Питомец профиля: погладить, покормить, поиграть.
 *
 * Каждое действие — раз в день от каждого игрока (и от хозяина тоже),
 * за действия питомец копит опыт и растёт в уровне. Если его давно не
 * кормили, он голодный и просит еды; если с ним недавно возились —
 * радостный.
 */
class PetService
{
    /** Действие => сколько опыта даёт. */
    public const ACTIONS = ['pet' => 1, 'feed' => 2, 'play' => 2];

    public const MAX_LEVEL = 30;

    private const HUNGRY_AFTER_HOURS = 24;

    private const HAPPY_WITHIN_HOURS = 6;

    public function stats(User $owner, ?User $viewer = null): array
    {
        $counts = DB::table('pet_interactions')
            ->where('owner_id', $owner->id)
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        $xp = 0;
        foreach (self::ACTIONS as $action => $points) {
            $xp += (int) ($counts[$action] ?? 0) * $points;
        }

        $level = $this->levelFor($xp);

        $lastFed = DB::table('pet_interactions')
            ->where('owner_id', $owner->id)
            ->where('action', 'feed')
            ->max('created_at');

        $lastAny = DB::table('pet_interactions')
            ->where('owner_id', $owner->id)
            ->max('created_at');

        $mood = match (true) {
            ! $lastFed || now()->diffInHours($lastFed, true) >= self::HUNGRY_AFTER_HOURS => 'hungry',
            $lastAny && now()->diffInHours($lastAny, true) < self::HAPPY_WITHIN_HOURS => 'happy',
            default => 'calm',
        };

        // Что зритель уже сделал сегодня
        $doneToday = $viewer
            ? DB::table('pet_interactions')
                ->where('owner_id', $owner->id)
                ->where('actor_id', $viewer->id)
                ->where('day', today()->toDateString())
                ->pluck('action')
                ->all()
            : [];

        // Последние, кто заботился о питомце
        $friends = DB::table('pet_interactions as pi')
            ->join('users as u', 'u.id', '=', 'pi.actor_id')
            ->where('pi.owner_id', $owner->id)
            ->where('pi.actor_id', '!=', $owner->id)
            ->orderByDesc('pi.created_at')
            ->limit(30)
            ->get(['u.id', 'u.username'])
            ->unique('id')
            ->take(5)
            ->values()
            ->all();

        return [
            'name' => $owner->pet_name,
            'xp' => $xp,
            'level' => $level,
            'level_xp' => $this->xpFor($level),
            'next_level_xp' => $level >= self::MAX_LEVEL ? null : $this->xpFor($level + 1),
            'mood' => $mood,
            'counts' => [
                'pet' => (int) ($counts['pet'] ?? 0),
                'feed' => (int) ($counts['feed'] ?? 0),
                'play' => (int) ($counts['play'] ?? 0),
            ],
            'done_today' => $doneToday,
            'friends' => $friends,
        ];
    }

    public function interact(User $owner, User $actor, string $action): array
    {
        if (! isset(self::ACTIONS[$action])) {
            throw ValidationException::withMessages(['action' => ['Такого с питомцем сделать нельзя.']]);
        }

        if (! $owner->profile_pet) {
            throw ValidationException::withMessages(['action' => ['У игрока нет питомца.']]);
        }

        $levelBefore = $this->stats($owner)['level'];

        try {
            DB::table('pet_interactions')->insert([
                'owner_id' => $owner->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'day' => today()->toDateString(),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            // Уникальный индекс: сегодня это уже делали
            throw ValidationException::withMessages(['action' => ['Сегодня ты уже это делал — приходи завтра.']]);
        }

        $stats = $this->stats($owner, $actor);
        $stats['level_up'] = $stats['level'] > $levelBefore;

        return $stats;
    }

    public function rename(User $owner, ?string $name): ?string
    {
        $name = mb_substr(trim(strip_tags((string) $name)), 0, 20);

        $owner->forceFill(['pet_name' => $name !== '' ? $name : null])->save();

        return $owner->pet_name;
    }

    /** Опыт на уровень растёт: 0, 5, 15, 30, 50… (5 · n(n−1)/2). */
    private function xpFor(int $level): int
    {
        return intdiv(5 * $level * ($level - 1), 2);
    }

    private function levelFor(int $xp): int
    {
        $level = 1;

        while ($level < self::MAX_LEVEL && $xp >= $this->xpFor($level + 1)) {
            $level++;
        }

        return $level;
    }
}
