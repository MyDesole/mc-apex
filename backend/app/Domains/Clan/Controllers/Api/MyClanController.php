<?php

namespace App\Domains\Clan\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Clan\Services\ClanOverviewService;
use App\Domains\Clan\Services\ClanTreasuryService;
use App\Domains\Shop\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MyClanController extends Controller
{
    /**
     * Дашборд моего клана — всё сразу.
     */
    public function index(Request $request): JsonResponse
    {
        $membership = $request->user()->clanMember;

        if (!$membership) {
            return response()->json(['clan' => null]);
        }

        $clan = $membership->clan()->withCount('members')->first();

        return response()->json([
            'clan' => $clan,
            'my_role' => $membership->role,
            'my_permissions' => [
                'news' => $membership->can('news'),
                'forum' => $membership->can('forum'),
                'applications' => $membership->can('applications'),
                'wars' => $membership->can('wars'),
                'resources' => $membership->can('resources'),
                'roles' => $membership->role === 'leader',
                'kick' => $membership->role === 'leader',
                'edit_clan' => $membership->role === 'leader',
            ],
            'stats' => [
                'members' => $clan->members()->count(),
                'applications' => $clan->applications()->count(),
                // Группировка обязательна: без неё AND/OR давал
                // «challenger = X OR (opponent = X AND status IN ...)»
                // и активными считались все войны, где клан — challenger.
                'wars_active' => \App\Domains\Clan\Models\ClanWar::whereIn('status', ['pending', 'accepted'])
                    ->where(function ($q) use ($clan) {
                        $q->where('challenger_clan_id', $clan->id)
                            ->orWhere('opponent_clan_id', $clan->id);
                    })
                    ->count(),
                'forum_topics' => $clan->forumTopics()->count(),
                'resources' => $clan->resources()->count(),
            ],
            // Купленное оформление подсветки: лидер выбирает из него
            'highlight_styles' => ShopService::clanHighlightStyles($request->user()),
        ]);
    }

    /** Вкладка «Обзор»: объявление, отметки, уровень, цели, лента. */
    public function overview(Request $request, ClanOverviewService $overview): JsonResponse
    {
        return response()->json($overview->overview($request->user()->clanMember));
    }

    /** Ежедневная отметка участника — опыт для уровня клана. */
    public function checkIn(Request $request, ClanOverviewService $overview): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $overview->checkIn($user, $user->clanMember->clan);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = "Отметка засчитана: +{$result['xp']} опыта клану";
        $message .= $result['coins'] > 0 ? " и +{$result['coins']} ApexCoin в казну." : '.';

        if ($result['level_up']) {
            $message .= " Клан достиг {$result['level']['level']} уровня!";
        }

        return response()->json([
            'message' => $message,
            ...$result,
        ]);
    }

    /** Объявление клана: пишут лидер и те, кому можно публиковать новости. */
    public function updateMotd(Request $request, ClanOverviewService $overview): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:' . ClanOverviewService::MOTD_MAX],
        ]);

        return response()->json([
            'motd' => $overview->updateMotd($request->user()->clanMember, $data['text'] ?? null),
        ]);
    }

    /** Сводка для меню сайта: отметка, войны, заявки. */
    public function pulse(Request $request, ClanOverviewService $overview): JsonResponse
    {
        return response()->json($overview->pulse($request->user()->clanMember));
    }

    /** Казна клана: баланс, вкладчики и история. */
    public function treasury(Request $request, ClanTreasuryService $treasury): JsonResponse
    {
        return response()->json($treasury->overview($request->user()->clanMember->clan));
    }

    /** Взнос участника в казну. */
    public function donate(Request $request, ClanTreasuryService $treasury): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:' . ClanTreasuryService::MAX_DONATION],
        ]);

        $user = $request->user();

        try {
            $clan = $treasury->donate($user, (int) $data['amount']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Вы скинули {$data['amount']} ApexCoin в казну клана.",
            'balance' => (int) $user->fresh()->apex_coins,
            ...$treasury->overview($clan),
        ]);
    }
}
