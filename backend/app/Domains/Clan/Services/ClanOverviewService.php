<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Models\ClanForumTopic;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Models\ClanTreasuryTransaction;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Clan\Support\ClanLevel;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Вкладка «Обзор» в «Моём клане»: объявление, отметки, уровень,
 * недельные цели, лучшие бойцы и лента активности.
 */
class ClanOverviewService
{
    public const MOTD_MAX = 500;

    private const FEED_LIMIT = 15;

    public function overview(ClanMember $membership): array
    {
        $clan = $membership->clan()->first();
        $user = $membership->user;
        $today = now()->toDateString();
        $membersCount = $clan->members()->count();

        $todayCheckins = ClanCheckin::query()
            ->where('clan_id', $clan->id)
            ->where('day', $today)
            ->with('user:id,username,avatar,minecraft_username')
            ->latest('id')
            ->get();

        return [
            'motd' => $this->motdPayload($clan),
            'can_edit_motd' => $membership->can('news'),
            'level' => $this->level($clan),
            'checkin' => [
                'done' => $todayCheckins->contains('user_id', $user->id),
                'streak' => $this->streak($user),
                'today_count' => $todayCheckins->count(),
                'members' => $membersCount,
                'today' => $todayCheckins->take(12)->map(fn (ClanCheckin $c) => $this->userCard($c->user))->filter()->values(),
            ],
            'goals' => $this->weeklyGoals($clan, $membersCount),
            'top_members' => $this->topMembers($clan),
            'wars' => $this->upcomingWars($clan),
            'treasury' => (int) $clan->treasury,
            'feed' => $this->feed($clan),
        ];
    }

    /**
     * Отметка за сегодня. Возвращает обновлённый обзор отметок.
     */
    public function checkIn(User $user, Clan $clan): array
    {
        $today = now()->toDateString();

        if (ClanCheckin::where('user_id', $user->id)->where('day', $today)->exists()) {
            throw new RuntimeException('Сегодня вы уже отмечались.');
        }

        try {
            ClanCheckin::create(['clan_id' => $clan->id, 'user_id' => $user->id, 'day' => $today]);
        } catch (QueryException) {
            // Двойной клик: вторая вставка упирается в уникальный индекс
            throw new RuntimeException('Сегодня вы уже отмечались.');
        }

        $levelBefore = (int) ($clan->level ?: 1);
        ClanLevel::sync($clan);

        // Монеты в казну — по уровню до отметки. Только тем, кто в клане
        // больше суток: иначе твинки вступали бы ради одной отметки.
        $coins = 0;
        $membership = $user->clanMember;
        $joinedAt = $membership?->joined_at ?? $membership?->created_at;

        if ($joinedAt && $joinedAt->lte(now()->subDay())) {
            $coins = ClanLevel::checkinCoins($levelBefore);
            app(ClanTreasuryService::class)->reward($clan, $user, $coins, 'Отметка участника');
        }

        return [
            'streak' => $this->streak($user),
            'xp' => ClanLevel::XP_PER_CHECKIN,
            'coins' => $coins,
            'level_up' => (int) $clan->level > $levelBefore,
            'level' => ClanLevel::progress($clan),
        ];
    }

    public function updateMotd(ClanMember $membership, ?string $text): array
    {
        $text = trim((string) $text);
        $clan = $membership->clan;

        $clan->forceFill([
            'motd' => $text === '' ? null : $text,
            'motd_updated_at' => $text === '' ? null : now(),
            'motd_by' => $text === '' ? null : $membership->user_id,
        ])->save();

        return $this->motdPayload($clan->fresh());
    }

    /**
     * Короткая сводка для меню сайта: есть ли повод зайти в клан.
     */
    public function pulse(ClanMember $membership): array
    {
        $clan = $membership->clan;

        return [
            'clan' => [
                'id' => $clan->id,
                'name' => $clan->name,
                'tag' => $clan->tag,
                'avatar_url' => $clan->avatar_url,
                'banner_color' => $clan->banner_color,
            ],
            'role' => $membership->role,
            'level' => (int) ($clan->level ?: 1),
            'checked_in' => ClanCheckin::where('user_id', $membership->user_id)
                ->where('day', now()->toDateString())
                ->exists(),
            'wars_active' => $this->activeWarsQuery($clan)->count(),
            'applications' => $membership->can('applications') ? $clan->applications()->count() : 0,
        ];
    }

    /** Уровень, опыт и бонусы клана (см. ClanLevel). */
    public function level(Clan $clan): array
    {
        return ClanLevel::progress(ClanLevel::sync($clan));
    }

    /** Сколько дней подряд игрок отмечался (сегодня или по вчерашний день). */
    private function streak(User $user): int
    {
        $days = ClanCheckin::where('user_id', $user->id)
            ->where('day', '>=', now()->subDays(400)->toDateString())
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->all();

        $cursor = now()->startOfDay();

        // Сегодня ещё не отметился — серия не сгорела, считаем со вчера
        if (($days[0] ?? null) !== $cursor->toDateString()) {
            $cursor->subDay();
        }

        $streak = 0;
        foreach ($days as $day) {
            if ($day !== $cursor->toDateString()) {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    private function weeklyGoals(Clan $clan, int $membersCount): array
    {
        $weekStart = now()->startOfWeek();

        $checkins = ClanCheckin::where('clan_id', $clan->id)
            ->where('day', '>=', $weekStart->toDateString())
            ->count();

        $wins = ClanWar::where('winner_clan_id', $clan->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $weekStart)
            ->count();

        $donated = (int) ClanTreasuryTransaction::where('clan_id', $clan->id)
            ->where('type', ClanTreasuryTransaction::TYPE_DONATION)
            ->where('created_at', '>=', $weekStart)
            ->sum('amount');

        $topics = ClanForumTopic::where('clan_id', $clan->id)
            ->where('created_at', '>=', $weekStart)
            ->count();

        return [
            'resets_at' => $weekStart->copy()->addWeek()->toIso8601String(),
            'items' => [
                ['id' => 'checkins', 'label' => 'Отметки участников', 'value' => $checkins, 'target' => max(10, $membersCount * 3)],
                ['id' => 'wins', 'label' => 'Победа в клановой войне', 'value' => $wins, 'target' => 1],
                ['id' => 'treasury', 'label' => 'Взносы в казну', 'value' => $donated, 'target' => 500],
                ['id' => 'forum', 'label' => 'Новые темы на форуме', 'value' => $topics, 'target' => 3],
            ],
        ];
    }

    private function topMembers(Clan $clan): Collection
    {
        return ClanMember::query()
            ->where('clan_id', $clan->id)
            ->with('user:id,username,avatar,minecraft_username,rating_points,tier,role')
            ->get()
            ->filter(fn (ClanMember $m) => $m->user)
            ->sortByDesc(fn (ClanMember $m) => (int) $m->user->rating_points)
            ->take(5)
            ->map(fn (ClanMember $m) => [
                ...$this->userCard($m->user),
                'role' => $m->role,
                'rating_points' => (int) $m->user->rating_points,
                'tier' => $m->user->tier,
            ])
            ->values();
    }

    private function activeWarsQuery(Clan $clan)
    {
        return ClanWar::whereIn('status', ['pending', 'accepted'])
            ->where(function ($q) use ($clan) {
                $q->where('challenger_clan_id', $clan->id)
                    ->orWhere('opponent_clan_id', $clan->id);
            });
    }

    private function upcomingWars(Clan $clan): Collection
    {
        return $this->activeWarsQuery($clan)
            ->with(['challenger:id,name,tag,avatar', 'opponent:id,name,tag,avatar'])
            ->withCount('participants')
            ->orderByRaw('scheduled_at IS NULL, scheduled_at')
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(function (ClanWar $war) use ($clan) {
                $enemy = $war->challenger_clan_id === $clan->id ? $war->opponent : $war->challenger;

                return [
                    'id' => $war->id,
                    'status' => $war->status,
                    'incoming' => $war->opponent_clan_id === $clan->id,
                    'scheduled_at' => $war->scheduled_at?->toIso8601String(),
                    'participants' => $war->participants_count,
                    'enemy' => $enemy ? [
                        'id' => $enemy->id,
                        'name' => $enemy->name,
                        'tag' => $enemy->tag,
                        'avatar_url' => $enemy->avatar_url,
                    ] : null,
                ];
            });
    }

    /**
     * Лента собирается из уже существующих данных: вступления, взносы,
     * итоги войн, темы форума и новости. Отдельный журнал не нужен.
     */
    private function feed(Clan $clan): Collection
    {
        $items = collect();

        ClanMember::where('clan_id', $clan->id)
            ->with('user:id,username,avatar,minecraft_username')
            ->latest('id')
            ->limit(6)
            ->get()
            ->each(function (ClanMember $m) use ($items) {
                $items->push([
                    'type' => 'join',
                    'user' => $this->userCard($m->user),
                    'text' => 'вступил(а) в клан',
                    'at' => ($m->joined_at ?? $m->created_at)?->toIso8601String(),
                ]);
            });

        ClanTreasuryTransaction::where('clan_id', $clan->id)
            ->where('type', ClanTreasuryTransaction::TYPE_DONATION)
            ->with('user:id,username,avatar,minecraft_username')
            ->latest('id')
            ->limit(6)
            ->get()
            ->each(function (ClanTreasuryTransaction $t) use ($items) {
                $items->push([
                    'type' => 'donation',
                    'user' => $this->userCard($t->user),
                    'text' => "скинул(а) {$t->amount} ApexCoin в казну",
                    'at' => $t->created_at?->toIso8601String(),
                ]);
            });

        ClanWar::where('status', 'completed')
            ->where(function ($q) use ($clan) {
                $q->where('challenger_clan_id', $clan->id)
                    ->orWhere('opponent_clan_id', $clan->id);
            })
            ->with(['challenger:id,tag', 'opponent:id,tag'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (ClanWar $war) use ($items, $clan) {
                $enemy = $war->challenger_clan_id === $clan->id ? $war->opponent : $war->challenger;
                $tag = $enemy?->tag ? "[{$enemy->tag}]" : 'соперником';
                $result = match (true) {
                    $war->winner_clan_id === $clan->id => 'win',
                    $war->winner_clan_id === null => 'draw',
                    default => 'loss',
                };

                $items->push([
                    'type' => "war_{$result}",
                    'user' => null,
                    'text' => match ($result) {
                        'win' => "Победа в КВ над {$tag}",
                        'draw' => "Ничья в КВ с {$tag}",
                        default => "Поражение в КВ от {$tag}",
                    },
                    'at' => $war->updated_at?->toIso8601String(),
                ]);
            });

        ClanForumTopic::where('clan_id', $clan->id)
            ->with('author:id,username,avatar,minecraft_username')
            ->latest('id')
            ->limit(5)
            ->get()
            ->each(function (ClanForumTopic $topic) use ($items) {
                $items->push([
                    'type' => 'topic',
                    'user' => $this->userCard($topic->author),
                    'text' => "создал(а) тему «{$topic->title}»",
                    'at' => $topic->created_at?->toIso8601String(),
                ]);
            });

        ClanEvent::where('clan_id', $clan->id)
            ->with('author:id,username,avatar,minecraft_username')
            ->latest('id')
            ->limit(5)
            ->get()
            ->each(function (ClanEvent $event) use ($items) {
                $items->push([
                    'type' => 'news',
                    'user' => $this->userCard($event->author),
                    'text' => "опубликовал(а) новость «{$event->title}»",
                    'at' => $event->created_at?->toIso8601String(),
                ]);
            });

        return $items
            ->filter(fn ($item) => $item['at'])
            ->sortByDesc('at')
            ->take(self::FEED_LIMIT)
            ->values();
    }

    private function motdPayload(Clan $clan): ?array
    {
        if (! $clan->motd) {
            return null;
        }

        $author = $clan->motd_by ? User::select('id', 'username', 'avatar', 'minecraft_username')->find($clan->motd_by) : null;

        return [
            'text' => $clan->motd,
            'updated_at' => $clan->motd_updated_at?->toIso8601String(),
            'author' => $this->userCard($author),
        ];
    }

    private function userCard(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'minecraft_username' => $user->minecraft_username,
        ] : null;
    }
}
