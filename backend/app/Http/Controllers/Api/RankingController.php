<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RankingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function index(RankingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $mode = $data['mode'] ?? 'overall';
        $limit = min((int) ($data['limit'] ?? 30), 100);
        // Курсор принимаем в любом из поддерживаемых видов
        $cursor = $request->cursor();

        $pvp = $this->aspectSum(
            'player_aspects_pvp',
            ['block_placing', 'rotka', 'movement', 'aim', 'game_sense']
        );

        $bedwars = $this->aspectSum(
            'player_aspects_bedwars',
            ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building']
        );

        // Общий рейтинг — сумма аспектов обоих режимов.
        // Раньше делилось на 2, и игрок с 1 баллом давал 0.5 -> ROUND 1,
        // а с 0.5 — 0, из-за чего он выпадал из рейтинга по условию score > 0.
        $score = match ($mode) {
            'pvp' => "ROUND($pvp)",
            'bedwars' => "ROUND($bedwars)",
            default => "ROUND($pvp + $bedwars)",
        };

        $query = User::query()
            ->select('users.*')
            ->selectRaw("$score AS rating_score")
            ->with('clanMember.clan:id,name,tag,banner_color')
            ->excludeStaff()
            ->whereRaw("$score > 0");

        if (!empty($data['search'])) {
            $query->where('username', 'like', "%{$data['search']}%");
        }

        if (!empty($data['tier'])) {
            $query->where('tier', $data['tier']);
        }

        if (!empty($data['clan_id'])) {
            $query->whereHas(
                'clanMember',
                fn ($q) => $q->where('clan_id', $data['clan_id'])
            );
        }

        if ($cursor) {
            $query->whereRaw(
                "($score < ? OR ($score = ? AND users.id < ?))",
                [
                    $cursor['score'] ?? 0,
                    $cursor['score'] ?? 0,
                    $cursor['id'] ?? 0,
                ]
            );
        }

        $rows = $query
            ->orderByDesc('rating_score')
            ->orderByDesc('users.id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        $offset = $cursor['offset'] ?? 0;

        $players = $rows->map(fn (User $user, $i) => [
            'position' => $offset + $i + 1,
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
        ]);

        $last = $rows->last();

        return response()->json([
            'data' => $players,
            'has_more' => $hasMore,
            'next_cursor' => $last ? [
                'score' => (int) $last->rating_score,
                'id' => $last->id,
                'offset' => $offset + $rows->count(),
            ] : null,
            'mode' => $mode,
        ]);
    }

    private function aspectSum(string $table, array $columns): string
    {
        $sum = implode(' + ', array_map(
            fn ($column) => "COALESCE($table.$column, 0)",
            $columns
        ));

        return "COALESCE((
            SELECT $sum
            FROM $table
            WHERE $table.user_id = users.id
        ), 0)";
    }
}
