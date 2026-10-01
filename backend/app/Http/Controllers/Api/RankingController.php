<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ranking\RankingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Рейтинг игроков с курсорной пагинацией.
 *
 * Раньше контроллер выгружал ВСЕХ пользователей в память, считал очки в PHP
 * и сортировал коллекцию — на большой базе это медленно и не масштабируется.
 * Здесь оценка считается в SQL, а страницы отдаются по курсору (keyset):
 * клиент присылает score+id последнего элемента и получает следующую порцию.
 */
class RankingController extends Controller
{
    /**
     * score_mode: overall | pvp | bedwars
     */
    public function index(RankingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $mode = $validated['mode'] ?? 'overall';
        $limit = (int) ($validated['limit'] ?? 30);

        $pvpSum = $this->aspectSum('player_aspects_pvp', ['block_placing', 'rotka', 'movement', 'aim', 'game_sense']);
        $bwSum = $this->aspectSum('player_aspects_bedwars', ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building']);

        $scoreExpr = match ($mode) {
            'pvp' => "CAST({$pvpSum} AS INTEGER)",
            'bedwars' => "CAST({$bwSum} AS INTEGER)",
            // Общий рейтинг — среднее двух режимов с ОКРУГЛЕНИЕМ,
            // как это делал прежний PHP-код: целочисленное деление
            // в SQL отбрасывало дробь и игроки выпадали из топа.
            default => "CAST(ROUND((({$pvpSum}) + ({$bwSum})) / 2.0) AS INTEGER)",
        };

        $query = User::query()
            ->select('users.*')
            ->selectRaw("{$scoreExpr} as rating_score")
            ->with('clanMember.clan:id,name,tag,banner_color')
            // Персонал в рейтинг не попадает, а медийка участвует наравне с игроками
            ->excludeStaff();

        if (! empty($validated['search'])) {
            $query->where('users.username', 'like', '%' . $validated['search'] . '%');
        }

        if (! empty($validated['tier'])) {
            $query->where('users.tier', $validated['tier']);
        }

        if (! empty($validated['clan_id'])) {
            $query->whereHas('clanMember', fn ($q) => $q->where('clan_id', $validated['clan_id']));
        }

        // Нулевые очки в рейтинг не попадают.
        // Через whereRaw, а не havingRaw: SQLite не принимает HAVING без агрегата.
        $query->whereRaw("{$scoreExpr} > 0");

        // Курсор: продолжаем строго после (score, id)
        if (! empty($validated['cursor_score']) || ! empty($validated['cursor_id'])) {
            $cursorScore = (int) ($validated['cursor_score'] ?? 0);
            $cursorId = (int) ($validated['cursor_id'] ?? 0);

            $query->whereRaw(
                "({$scoreExpr} < ? OR ({$scoreExpr} = ? AND users.id < ?))",
                [$cursorScore, $cursorScore, $cursorId]
            );
        }

        $query->orderByDesc('rating_score')->orderByDesc('users.id');

        // Берём на один больше, чтобы понять, есть ли следующая страница
        $rows = $query->limit($limit + 1)->get();

        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->slice(0, $limit);
        }

        // Позиция в общем рейтинге считается от начала
        $offset = 0;

        if (! empty($validated['cursor_score']) || ! empty($validated['cursor_id'])) {
            $offset = (int) ($request->query('offset', 0));
        }

        $players = $rows->values()->map(function (User $user, int $index) use ($offset) {
            return [
                'position' => $offset + $index + 1,
                'id' => $user->id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'tier' => $user->tier,
                'tier_score' => $user->tier_score,
                'rating_score' => (int) $user->rating_score,
                'clan_tag' => $user->clan_tag,
                'clan_color' => $user->clan_color,
                'is_verified' => (bool) $user->is_verified,
                'accent_color' => $user->accent_color,
                'banner_color' => $user->banner_color,
                'status' => $user->status,
                'quote' => $user->quote,
                'avatar_frame' => $user->avatar_frame,
                'profile_effect' => $user->profile_effect,
            ];
        });

        $last = $rows->last();

        return response()->json([
            'data' => $players,
            'has_more' => $hasMore,
            'next_cursor' => $last ? [
                'score' => (int) $last->rating_score,
                'id' => $last->id,
                'offset' => $offset + $players->count(),
            ] : null,
            'mode' => $mode,
        ]);
    }

    /**
     * Сумма аспектов из связанной таблицы как SQL-подзапрос.
     *
     * COALESCE снаружи обязателен: если у игрока нет записи аспектов,
     * подзапрос возвращает NULL и вся сумма превращается в NULL,
     * из-за чего игрок выпадал из рейтинга целиком.
     */
    private function aspectSum(string $table, array $columns): string
    {
        $sum = implode(' + ', array_map(
            fn ($c) => "COALESCE({$table}.{$c}, 0)",
            $columns
        ));

        return "COALESCE((SELECT ({$sum}) FROM {$table} WHERE {$table}.user_id = users.id), 0)";
    }
}
