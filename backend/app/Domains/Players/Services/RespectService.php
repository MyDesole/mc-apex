<?php

namespace App\Domains\Players\Services;

use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Респекты и гости профиля.
 *
 * Респект: раз в день от игрока чужому профилю. Счёт копится всегда,
 * неделя — для топа «самые уважаемые». Гости: кто заходил, видит только
 * хозяин профиля.
 */
class RespectService
{
    private const USER_FIELDS = ['u.id', 'u.username', 'u.avatar', 'u.tier', 'u.name_effect', 'u.name_icon'];

    public function summary(User $owner, ?User $viewer): array
    {
        $total = DB::table('profile_respects')->where('owner_id', $owner->id)->count();
        $week = DB::table('profile_respects')
            ->where('owner_id', $owner->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recent = DB::table('profile_respects as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.owner_id', $owner->id)
            ->orderByDesc('r.created_at')
            ->limit(40)
            ->get([...self::USER_FIELDS, 'r.created_at'])
            ->unique('id')
            ->take(8)
            ->values()
            ->map(fn ($row) => $this->person($row))
            ->all();

        return [
            'total' => $total,
            'week' => $week,
            'rank' => $week > 0 ? $this->weekRank($owner->id, $week) : null,
            'given_today' => $viewer
                ? DB::table('profile_respects')
                    ->where('owner_id', $owner->id)
                    ->where('user_id', $viewer->id)
                    ->where('day', today()->toDateString())
                    ->exists()
                : false,
            'can_give' => $viewer !== null && $viewer->id !== $owner->id,
            'recent' => $recent,
        ];
    }

    public function give(User $owner, User $actor): array
    {
        if ($owner->id === $actor->id) {
            throw ValidationException::withMessages(['respect' => ['Себе респект не дают 😉']]);
        }

        try {
            DB::table('profile_respects')->insert([
                'owner_id' => $owner->id,
                'user_id' => $actor->id,
                'day' => today()->toDateString(),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['respect' => ['Сегодня ты уже давал респект — приходи завтра.']]);
        }

        return $this->summary($owner, $actor);
    }

    /** Отметить, что игрок зашёл в профиль (себя не считаем). */
    public function visit(User $owner, User $visitor): void
    {
        if ($owner->id === $visitor->id) {
            return;
        }

        DB::table('profile_visits')->upsert(
            [[
                'owner_id' => $owner->id,
                'visitor_id' => $visitor->id,
                'day' => today()->toDateString(),
                'last_at' => now(),
            ]],
            ['owner_id', 'visitor_id', 'day'],
            ['last_at'],
        );
    }

    /** Гости профиля за неделю: последние зашедшие и сколько их всего. */
    public function guests(User $owner): array
    {
        $since = now()->subDays(7);

        $rows = DB::table('profile_visits as v')
            ->join('users as u', 'u.id', '=', 'v.visitor_id')
            ->where('v.owner_id', $owner->id)
            ->where('v.last_at', '>=', $since)
            ->orderByDesc('v.last_at')
            ->get([...self::USER_FIELDS, 'v.last_at']);

        $people = $rows->unique('id')->values();

        return [
            'week_unique' => $people->count(),
            'week_visits' => $rows->count(),
            'guests' => $people->take(20)->map(fn ($row) => $this->person($row) + [
                'last_at' => \Illuminate\Support\Carbon::parse($row->last_at)->toIso8601String(),
            ])->all(),
        ];
    }

    /** Топ недели: у кого больше всего респектов за 7 дней. */
    public function leaderboard(int $limit = 10): array
    {
        return DB::table('profile_respects as r')
            ->join('users as u', 'u.id', '=', 'r.owner_id')
            ->where('r.created_at', '>=', now()->subDays(7))
            ->groupBy(self::USER_FIELDS)
            ->orderByDesc('respects')
            ->limit($limit)
            ->get([...self::USER_FIELDS, DB::raw('count(*) as respects')])
            ->map(fn ($row) => $this->person($row) + ['respects' => (int) $row->respects])
            ->all();
    }

    private function weekRank(int $ownerId, int $week): int
    {
        $ahead = DB::table('profile_respects')
            ->where('created_at', '>=', now()->subDays(7))
            ->select('owner_id')
            ->groupBy('owner_id')
            ->havingRaw('count(*) > ?', [$week])
            ->get()
            ->count();

        return $ahead + 1;
    }

    private function person(object $row): array
    {
        return [
            'id' => $row->id,
            'username' => $row->username,
            'avatar_url' => $row->avatar ? asset('storage/' . $row->avatar) : null,
            'tier' => $row->tier,
            'name_effect' => $row->name_effect,
            'name_icon' => $row->name_icon,
        ];
    }
}
