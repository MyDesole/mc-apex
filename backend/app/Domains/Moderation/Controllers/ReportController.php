<?php

namespace App\Domains\Moderation\Controllers;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Moderation\Models\Report;
use App\Domains\Moderation\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Жалобы: подача игроком, очередь и решения модераторов, журнал действий.
 */
class ReportController extends Controller
{
    private const RELATIONS = [
        'reporter:id,username,avatar',
        'target:id,username,avatar,role,is_banned,banned_until',
        'moderator:id,username',
    ];

    public function __construct(private ReportService $reports) {}

    /** Причины жалоб — для формы на сайте. */
    public function reasons(): JsonResponse
    {
        return response()->json([
            'reasons' => collect(Report::REASONS)->map(fn ($label, $key) => compact('key', 'label'))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Report::SUBJECTS)],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(array_keys(Report::REASONS))],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $this->reports->submit(
            $request->user(),
            $data['type'],
            (int) $data['id'],
            $data['reason'],
            $data['comment'] ?? null,
        );

        return response()->json(['report' => ['id' => $report->id, 'status' => $report->status]], 201);
    }

    /** Очередь жалоб для модераторов. */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', Report::STATUS_OPEN);

        $reports = Report::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('target'), fn ($q, $id) => $q->where('target_user_id', $id))
            ->with(self::RELATIONS)
            ->orderBy($status === Report::STATUS_OPEN ? 'created_at' : 'resolved_at', $status === Report::STATUS_OPEN ? 'asc' : 'desc')
            ->paginate(30);

        // Сколько всего жалоб на этого же игрока — повод присмотреться
        $repeat = Report::query()
            ->whereIn('target_user_id', $reports->pluck('target_user_id')->filter())
            ->selectRaw('target_user_id, COUNT(*) as total')
            ->groupBy('target_user_id')
            ->pluck('total', 'target_user_id');

        $reports->through(fn (Report $r) => [
            ...$r->toArray(),
            'reason_label' => Report::REASONS[$r->reason] ?? $r->reason,
            'reports_on_target' => (int) ($repeat[$r->target_user_id] ?? 0),
        ]);

        return response()->json([
            ...$reports->toArray(),
            'reasons' => Report::REASONS,
        ]);
    }

    public function resolve(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(Report::ACTIONS)],
            'note' => ['nullable', 'string', 'max:500'],
            // Пусто — бессрочно
            'ban_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $report = $this->reports->resolve(
            $report,
            $request->user(),
            $data['action'],
            $data['note'] ?? null,
            $data['ban_days'] ?? null,
        );

        return response()->json(['report' => $report]);
    }

    /** Быстрый поиск игрока по нику — открыть досье. */
    public function searchPlayers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['players' => []]);
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $players = \App\Domains\Users\Models\User::query()
            ->whereRaw("username LIKE ? ESCAPE '!'", [$like])
            ->orderByRaw('LENGTH(username)')
            ->limit(8)
            ->get(['id', 'username', 'avatar', 'role', 'tier', 'is_banned']);

        return response()->json(['players' => $players->map(fn ($u) => [
            'id' => $u->id,
            'username' => $u->username,
            'avatar_url' => $u->avatar_url,
            'role' => $u->role,
            'tier' => $u->tier,
            'is_banned' => (bool) $u->is_banned,
        ])]);
    }

    /** Журнал действий персонала. */
    public function log(Request $request): JsonResponse
    {
        $logs = ModerationLog::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', "{$action}%"))
            ->when($request->query('target'), fn ($q, $id) => $q->where('target_user_id', $id))
            ->with(['actor:id,username,avatar,role', 'target:id,username,avatar'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(40);

        $logs->through(fn (ModerationLog $log) => [
            ...$log->toArray(),
            'label' => ModerationLog::LABELS[$log->action] ?? $log->action,
        ]);

        return response()->json($logs);
    }
}
