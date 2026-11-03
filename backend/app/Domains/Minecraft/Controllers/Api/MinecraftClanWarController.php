<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Clan\Services\ClanWarService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Клановые войны для игрового сервера: лидер видит принятые войны своего
 * клана, а итог матча уходит в ту же ClanWar, что и результат с сайта.
 */
class MinecraftClanWarController extends Controller
{
    public function __construct(private readonly ClanWarService $wars)
    {
    }

    /** Принятые войны клана, в котором ник - лидер или офицер. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leader' => ['required', 'string', 'max:32'],
        ]);

        $user = User::query()->where('minecraft_username', $data['leader'])->first();
        $member = $user?->clanMember;

        if (! $member || ! in_array($member->role, ['leader', 'officer'], true)) {
            return response()->json(['wars' => []]);
        }

        $wars = ClanWar::query()
            ->where('status', 'accepted')
            ->where(function ($q) use ($member) {
                $q->where('challenger_clan_id', $member->clan_id)
                    ->orWhere('opponent_clan_id', $member->clan_id);
            })
            ->with(['challenger.leader', 'opponent.leader'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        return response()->json([
            'wars' => $wars->map(fn (ClanWar $war) => [
                'id' => $war->id,
                'scheduled_at' => $war->scheduled_at?->toIso8601String(),
                'my_side' => $war->challenger_clan_id === $member->clan_id ? 'challenger' : 'opponent',
                'challenger' => $this->clan($war->challenger),
                'opponent' => $this->clan($war->opponent),
            ])->all(),
        ]);
    }

    /** Итог войны: счёт по матчам, режим и список матчей в заметки. */
    public function result(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'challenger_score' => ['required', 'integer', 'min:0', 'max:100'],
            'opponent_score' => ['required', 'integer', 'min:0', 'max:100'],
            'mode' => ['nullable', 'string', 'max:32'],
            'games' => ['nullable', 'array', 'max:100'],
            'games.*.name' => ['required', 'string', 'max:64'],
            'games.*.winner' => ['required', 'in:challenger,opponent,none'],
        ]);

        $war = ClanWar::find($id);

        if (! $war) {
            return response()->json(['message' => 'Война не найдена.'], 404);
        }

        if ($war->status !== 'accepted') {
            return response()->json(['message' => 'Война не в статусе принятой.'], 422);
        }

        $lines = [sprintf('Итог на сервере (%s): %d : %d.', $data['mode'] ?? 'дуэли', $data['challenger_score'], $data['opponent_score'])];

        foreach ($data['games'] ?? [] as $i => $game) {
            $who = ['challenger' => 'вызвавший клан', 'opponent' => 'принявший клан', 'none' => 'ничья'][$game['winner']];
            $lines[] = sprintf('%d. %s - %s', $i + 1, $game['name'], $who);
        }

        // Результат вносит лидер вызвавшего клана: так же, как если бы он нажал кнопку на сайте
        $leader = $war->challenger?->leader;

        if (! $leader) {
            return response()->json(['message' => 'У клана нет лидера.'], 422);
        }

        $done = $this->wars->complete($leader, $war, [
            'challenger_score' => $data['challenger_score'],
            'opponent_score' => $data['opponent_score'],
            'notes' => trim(($war->notes ? $war->notes . "\n\n" : '') . implode("\n", $lines)),
        ]);

        return response()->json([
            'ok' => true,
            'war' => ['id' => $done->id, 'status' => $done->status, 'outcome' => $done->outcome],
        ]);
    }

    private function clan(?Clan $clan): ?array
    {
        if (! $clan) {
            return null;
        }

        $nicks = $clan->members()
            ->with('user:id,minecraft_username')
            ->get()
            ->map(fn ($m) => $m->user?->minecraft_username)
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'leader' => $clan->leader?->minecraft_username,
            'members' => $nicks,
        ];
    }
}
