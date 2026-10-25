<?php

namespace App\Domains\Moderation\Controllers;

use App\Domains\Clan\Models\ClanWar;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Moderation\Models\Report;
use App\Domains\Players\Services\PlayerProfileService;
use App\Domains\Players\Services\RatingService;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestAppeal;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Дашборд админки, счётчики для бокового меню и досье игрока.
 */
class DashboardController extends Controller
{
    private const ONLINE_MINUTES = 5;

    /** Что требует внимания — для бейджей в меню, опрашивается раз в минуту. */
    public function counters(): JsonResponse
    {
        return response()->json([
            'reports' => Report::where('status', Report::STATUS_OPEN)->count(),
            'appeals' => TierTestAppeal::where('status', TierTestAppeal::STATUS_PENDING)->count(),
            'tests' => TierTest::where('status', 'pending')->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->isAdmin();

        return response()->json([
            'attention' => $this->attention(),
            'queue' => $this->queue(),
            'players' => $this->players(),
            'testers' => $this->testers(),
            'tests_by_day' => $this->testsByDay(),
            'economy' => $isAdmin ? $this->economy() : null,
            'recent_actions' => ModerationLog::query()
                ->with(['actor:id,username', 'target:id,username'])
                ->latest('created_at')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (ModerationLog $log) => [
                    ...$log->toArray(),
                    'label' => ModerationLog::LABELS[$log->action] ?? $log->action,
                ]),
        ]);
    }

    /** Досье игрока: всё, что нужно модератору, на одной карточке. */
    public function dossier(Request $request, User $user, PlayerProfileService $profiles, RatingService $rating): JsonResponse
    {
        $isAdmin = $request->user()->isAdmin();
        [$position, $total] = $profiles->rankOf($user);

        $user->loadCount(['achievements']);
        $user->load('clanMember.clan:id,name,tag,banner_color');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                // Почта — только администратору
                'email' => $isAdmin ? $user->email : null,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
                'tier' => $user->tier,
                'tier_score' => (float) $user->tier_score,
                'rating_points' => (int) $user->rating_points,
                'apex_coins' => $isAdmin ? (int) $user->apex_coins : null,
                'minecraft_username' => $user->minecraft_username,
                'is_verified' => (bool) $user->is_verified,
                'is_banned' => (bool) $user->is_banned,
                'ban_reason' => $user->ban_reason,
                'banned_until' => $user->banned_until,
                'created_at' => $user->created_at,
                'last_seen_at' => $user->last_seen_at,
                'achievements_count' => $user->achievements_count,
                'clan' => $user->clanMember?->clan,
            ],
            'rank' => ['position' => $position, 'total' => $total],
            'rating' => $user->isStaff() ? null : $rating->breakdown($user),
            'tier_tests' => TierTest::where('user_id', $user->id)
                ->with('tester:id,username')
                ->latest()
                ->limit(10)
                ->get(['id', 'user_id', 'tester_id', 'mode', 'status', 'result_tier', 'result_score', 'created_at', 'completed_at']),
            'tests_conducted' => TierTest::where('tester_id', $user->id)->where('status', 'completed')->count(),
            'reports_against' => Report::where('target_user_id', $user->id)
                ->with('reporter:id,username')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Report $r) => [...$r->toArray(), 'reason_label' => Report::REASONS[$r->reason] ?? $r->reason]),
            'reports_against_total' => Report::where('target_user_id', $user->id)->count(),
            'reports_filed' => Report::where('reporter_id', $user->id)->count(),
            'moderation' => ModerationLog::where('target_user_id', $user->id)
                ->with('actor:id,username')
                ->latest('created_at')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (ModerationLog $log) => [...$log->toArray(), 'label' => ModerationLog::LABELS[$log->action] ?? $log->action]),
            'transactions' => $isAdmin
                ? CoinTransaction::where('user_id', $user->id)->latest()->limit(10)
                    ->get(['id', 'amount', 'balance_after', 'source', 'description', 'created_at'])
                : null,
        ]);
    }

    /* ----------------------------- Блоки дашборда ----------------------------- */

    private function attention(): array
    {
        return [
            'reports' => Report::where('status', Report::STATUS_OPEN)->count(),
            'appeals' => TierTestAppeal::where('status', TierTestAppeal::STATUS_PENDING)->count(),
            'tests_pending' => TierTest::where('status', 'pending')->count(),
            'wars_pending' => ClanWar::whereIn('status', ['pending', 'accepted'])->count(),
            'tournaments_active' => Tournament::whereIn('status', ['registration', 'ongoing'])->count(),
        ];
    }

    /** Очередь тир-тестов: сколько ждут, как давно и как быстро их берут. */
    private function queue(): array
    {
        $oldest = TierTest::where('status', 'pending')->oldest()->value('created_at');

        // Время от заявки до того, как тестер её взял, за последние 30 дней
        $waits = TierTest::query()
            ->whereNotNull('claimed_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['created_at', 'claimed_at'])
            ->map(fn (TierTest $t) => $t->created_at->diffInMinutes($t->claimed_at));

        return [
            'pending' => TierTest::where('status', 'pending')->count(),
            'in_progress' => TierTest::where('status', 'in_progress')->count(),
            'oldest_pending_at' => $oldest ? Carbon::parse($oldest)->toIso8601String() : null,
            'avg_wait_minutes' => $waits->isEmpty() ? null : (int) round($waits->avg()),
            'completed_week' => TierTest::where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function players(): array
    {
        return [
            'total' => User::count(),
            'new_today' => User::where('created_at', '>=', now()->startOfDay())->count(),
            'new_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'online' => User::where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->count(),
            'active_week' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
            'banned' => User::where('is_banned', true)->count(),
        ];
    }

    /** Тестеры: кто сколько провёл за неделю и кто давно не заходил. */
    private function testers(): array
    {
        $week = TierTest::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->selectRaw('tester_id, COUNT(*) as total')
            ->groupBy('tester_id')
            ->pluck('total', 'tester_id');

        return User::query()
            ->whereIn('role', ['tester', 'admin'])
            ->get(['id', 'username', 'avatar', 'role', 'last_seen_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'role' => $u->role,
                'tests_week' => (int) ($week[$u->id] ?? 0),
                'last_seen_at' => $u->last_seen_at,
            ])
            ->sortByDesc('tests_week')
            ->values()
            ->all();
    }

    /** Завершённые тесты по дням за две недели. */
    private function testsByDay(): array
    {
        $from = now()->startOfDay()->subDays(13);

        $byDay = TierTest::where('status', 'completed')
            ->where('completed_at', '>=', $from)
            ->pluck('completed_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m-d'));

        return collect(range(0, 13))
            ->map(function ($i) use ($from, $byDay) {
                $day = $from->copy()->addDays($i)->format('Y-m-d');

                return ['date' => $day, 'tests' => (int) ($byDay[$day] ?? 0)];
            })
            ->all();
    }

    /** ApexCoin: сколько на руках, сколько выдано и потрачено за неделю. */
    private function economy(): array
    {
        $week = CoinTransaction::where('created_at', '>=', now()->subDays(7));

        return [
            'in_circulation' => (int) User::sum('apex_coins'),
            'issued_week' => (int) (clone $week)->where('amount', '>', 0)
                ->whereNotIn('source', [CoinTransaction::SOURCE_GIFT_IN])->sum('amount'),
            'spent_week' => (int) abs((clone $week)->where('source', CoinTransaction::SOURCE_PURCHASE)->sum('amount')),
            'by_source' => (clone $week)->where('amount', '>', 0)
                ->selectRaw('source, SUM(amount) as total')
                ->groupBy('source')
                ->pluck('total', 'source'),
        ];
    }
}
