<?php

namespace App\Domains\Moderation\Services;

use App\Domains\Chat\Models\Message;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Moderation\Models\Report;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestAppeal;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Что происходит на сайте: лента событий, динамика и общие счётчики для админского дашборда.
 *
 * Каждый источник событий читается отдельно и в try/catch: сбой одной таблицы
 * не должен ронять весь дашборд.
 */
class AdminOverviewService
{
    /** Лента событий: последние действия на сайте со всех сторон, новые сверху. */
    public function activity(int $limit = 40): array
    {
        $events = collect();

        $events = $events->merge($this->source(fn () => User::query()->latest()->limit(15)->get(['id', 'username', 'created_at'])
            ->map(fn (User $u) => $this->event('signup', 'Новый игрок', $u->username, $u->created_at, $u->id))));

        $events = $events->merge($this->source(fn () => TierTest::query()->with('user:id,username')->latest()->limit(15)->get()
            ->map(function (TierTest $t) {
                $done = $t->status === 'completed';

                return $this->event(
                    $done ? 'test_done' : 'test_new',
                    $done ? 'Тир-тест проведён' : 'Заявка на тир-тест',
                    ($t->user?->username ?? '—') . ($done && $t->result_tier ? ' → ' . $t->result_tier : ''),
                    $done ? ($t->completed_at ?? $t->updated_at) : $t->created_at,
                    $t->user_id,
                );
            })));

        $events = $events->merge($this->source(fn () => TierTestAppeal::query()->with('user:id,username')->latest()->limit(8)->get()
            ->map(fn (TierTestAppeal $a) => $this->event('appeal', 'Апелляция на тир-тест', $a->user?->username ?? '—', $a->created_at, $a->user_id))));

        $events = $events->merge($this->source(fn () => Report::query()->with('reporter:id,username', 'target:id,username')->latest()->limit(10)->get()
            ->map(fn (Report $r) => $this->event(
                'report',
                'Жалоба',
                ($r->reporter?->username ?? '—') . ' → ' . ($r->target?->username ?? '—'),
                $r->created_at,
                $r->target_user_id,
            ))));

        $events = $events->merge($this->source(fn () => SupportTicket::query()->with('user:id,username')->latest()->limit(8)->get()
            ->map(fn (SupportTicket $t) => $this->event('support', 'Обращение в поддержку', ($t->user?->username ?? '—') . ': ' . mb_strimwidth((string) $t->subject, 0, 60, '…'), $t->created_at, $t->user_id))));

        $events = $events->merge($this->source(fn () => ForumTopic::query()->with('author:id,username')->latest()->limit(10)->get()
            ->map(fn (ForumTopic $t) => $this->event('forum', 'Тема на форуме', mb_strimwidth((string) $t->title, 0, 70, '…') . ' — ' . ($t->author?->username ?? '—'), $t->created_at, $t->author_id))));

        $events = $events->merge($this->source(fn () => Clan::query()->with('leader:id,username')->latest()->limit(8)->get()
            ->map(fn (Clan $c) => $this->event('clan', 'Новый клан', '[' . $c->tag . '] ' . $c->name . ' — ' . ($c->leader?->username ?? '—'), $c->created_at, $c->leader_id))));

        $events = $events->merge($this->source(fn () => ClanWar::query()->latest()->limit(8)->get()
            ->map(fn (ClanWar $w) => $this->event('war', 'Клановая война', 'статус: ' . $w->status, $w->created_at))));

        $events = $events->merge($this->source(fn () => CoinTransaction::query()
            ->where('source', CoinTransaction::SOURCE_PURCHASE)->with('user:id,username')->latest()->limit(10)->get()
            ->map(fn (CoinTransaction $c) => $this->event('purchase', 'Покупка в магазине', ($c->user?->username ?? '—') . ': ' . mb_strimwidth((string) $c->description, 0, 60, '…'), $c->created_at, $c->user_id))));

        return $events
            ->filter(fn ($e) => $e['at'] !== null)
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Динамика за 14 дней: регистрации, заявки на тесты, темы форума. */
    public function growth(): array
    {
        $from = now()->startOfDay()->subDays(13);

        $series = fn (string $table, string $column = 'created_at') => $this->source(
            fn () => DB::table($table)->where($column, '>=', $from)->pluck($column)
                ->countBy(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
        ) ?: collect();

        $signups = $series('users');
        $tests = $series('tier_tests');
        $topics = $series('forum_topics');
        $logins = $this->source(fn () => User::where('last_seen_at', '>=', $from)->pluck('last_seen_at')
            ->countBy(fn ($d) => Carbon::parse($d)->format('Y-m-d'))) ?: collect();

        return collect(range(0, 13))->map(function ($i) use ($from, $signups, $tests, $topics, $logins) {
            $day = $from->copy()->addDays($i)->format('Y-m-d');

            return [
                'date' => $day,
                'signups' => (int) ($signups[$day] ?? 0),
                'tests' => (int) ($tests[$day] ?? 0),
                'topics' => (int) ($topics[$day] ?? 0),
                // Сколько игроков в последний раз заходили в этот день (оценка активности)
                'last_seen' => (int) ($logins[$day] ?? 0),
            ];
        })->all();
    }

    /** Общие счётчики сайта за всё время и за сутки. */
    public function totals(): array
    {
        $day = now()->subDay();

        return [
            'clans' => $this->count(fn () => Clan::count()),
            'forum_topics' => $this->count(fn () => ForumTopic::count()),
            'forum_replies' => $this->count(fn () => ForumReply::count()),
            'forum_posts_day' => $this->count(fn () => ForumTopic::where('created_at', '>=', $day)->count()
                + ForumReply::where('created_at', '>=', $day)->count()),
            'messages_day' => $this->count(fn () => Message::where('created_at', '>=', $day)->count()),
            'tests_total' => $this->count(fn () => TierTest::where('status', 'completed')->count()),
            'tests_day' => $this->count(fn () => TierTest::where('created_at', '>=', $day)->count()),
            'tournaments' => $this->count(fn () => Tournament::count()),
            'tickets_open' => $this->count(fn () => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count()),
            'purchases_week' => $this->count(fn () => CoinTransaction::where('source', CoinTransaction::SOURCE_PURCHASE)
                ->where('created_at', '>=', now()->subDays(7))->count()),
            'bans_week' => $this->count(fn () => User::where('is_banned', true)->where('updated_at', '>=', now()->subDays(7))->count()),
        ];
    }

    private function event(string $type, string $title, string $text, $at, ?int $userId = null): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'text' => $text,
            'at' => $at ? Carbon::parse($at)->toIso8601String() : null,
            'user_id' => $userId,
        ];
    }

    /** @return Collection<int, array<string, mixed>>|Collection|null */
    private function source(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function count(callable $fn): ?int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
