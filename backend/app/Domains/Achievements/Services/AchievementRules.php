<?php

namespace App\Domains\Achievements\Services;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Chat\Models\Message;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Friends\Models\Friendship;
use App\Domains\Players\Models\ProfileRecommendation;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Users\Models\User;

/**
 * Условия ачивок, разложенные по группам.
 *
 * Группы нужны, чтобы проверять только то, что могло измениться:
 * новый ответ на форуме проверяет форумные ачивки, а не пересчитывает
 * клан, тиры и рейтинг. Полная проверка — все группы сразу.
 */
class AchievementRules
{
    public const GROUPS = [
        'clan', 'tiers', 'tests', 'aspects', 'social', 'forum',
        'chat', 'shop', 'tournaments', 'minecraft', 'profile', 'top', 'bridge',
    ];

    /** Порог аспекта для «мастерских» ачивок (максимум — 20). */
    private const ASPECT_MASTER = 18;

    /** Порог каждого аспекта для «Универсала». */
    private const ASPECT_ALL_ROUND = 15;

    /** Ачивка за аспект: какой ключ какого кода. */
    private const ASPECT_CODES = [
        'block_placing' => 'block_master',
        'rotka' => 'rod_master',
        'movement' => 'movement_master',
        'aim' => 'aim_master',
        'game_sense' => 'tactician',
    ];

    /**
     * Коды, условия которых выполнены.
     *
     * @param  array<int, string>|null  $groups  null — все группы
     * @return array<int, string>
     */
    public function eligible(User $user, ?array $groups = null): array
    {
        $codes = [];

        foreach ($groups ?? self::GROUPS as $group) {
            $method = 'group' . ucfirst($group);

            if (method_exists($this, $method)) {
                array_push($codes, ...$this->{$method}($user));
            }
        }

        return array_values(array_unique($codes));
    }

    /* ------------------------------ Клан ------------------------------ */

    private function groupClan(User $user): array
    {
        $member = $user->clanMember;

        if (! $member) {
            return [];
        }

        $codes = ['clan_joined'];

        if ($member->role === 'leader') {
            $codes[] = 'clan_leader';
        } elseif ($member->role === 'officer') {
            $codes[] = 'clan_officer';
        }

        $wins = ClanWar::where('winner_clan_id', $member->clan_id)->count();

        $codes = [...$codes, ...$this->thresholds($wins, [1 => 'clan_war_win', 5 => 'clan_war_5', 10 => 'clan_war_10'])];

        $clan = $member->clan;

        if ($clan && (int) $clan->power >= 1000) {
            $codes[] = 'clan_power_1000';
        }

        if ($clan && $clan->members()->count() >= 10) {
            $codes[] = 'clan_squad';
        }

        return $codes;
    }

    /* ------------------------------ Тиры ------------------------------ */

    private function groupTiers(User $user): array
    {
        $order = ['E' => 'tier_e', 'D' => 'tier_d', 'C' => 'tier_c', 'B' => 'tier_b', 'A' => 'tier_a'];

        // S и S+ включают все тиры ниже
        if (in_array($user->tier, ['S', 'S+'], true)) {
            $codes = [...array_values($order), 'tier_s'];

            if ($user->tier === 'S+') {
                $codes[] = 'tier_s_plus';
            }

            return $codes;
        }

        $codes = [];

        foreach ($order as $tier => $code) {
            $codes[] = $code;

            if ($tier === $user->tier) {
                return $codes;
            }
        }

        // Тира нет или он неизвестен — ничего не выдаём
        return [];
    }

    /* ------------------------------ Тир-тесты ------------------------------ */

    private function groupTests(User $user): array
    {
        $tests = TierTest::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->get(['mode', 'result_score']);

        $codes = $this->thresholds($tests->count(), [
            1 => 'tier_test_first',
            5 => 'tier_test_5',
            10 => 'tier_test_10',
        ]);

        // Рост: в каком-то режиме результат стал выше прошлого
        $best = [];

        foreach ($tests as $test) {
            $score = (float) $test->result_score;

            if (isset($best[$test->mode]) && $score > $best[$test->mode]) {
                $codes[] = 'tier_comeback';
                break;
            }

            $best[$test->mode] = max($best[$test->mode] ?? 0, $score);
        }

        return $codes;
    }

    /* ------------------------------ Аспекты ------------------------------ */

    private function groupAspects(User $user): array
    {
        $codes = [];

        $pvp = $user->aspectPvp;
        $bedwars = $user->aspectBedwars;

        if ($pvp) {
            $values = [];

            foreach (self::ASPECT_CODES as $field => $code) {
                $value = (float) ($pvp->{$field} ?? 0);
                $values[] = $value;

                if ($value >= self::ASPECT_MASTER) {
                    $codes[] = $code;
                }
            }

            if (max($values) >= 20) {
                $codes[] = 'aspect_perfect';
            }

            if (min($values) >= self::ASPECT_ALL_ROUND) {
                $codes[] = 'all_rounder';
            }
        }

        $hasPvp = $pvp && $pvp->sum() > 0;
        $hasBedwars = $bedwars && $bedwars->sum() > 0;

        if ($hasBedwars) {
            $codes[] = 'bedwars_player';
        }

        if ($hasPvp && $hasBedwars) {
            $codes[] = 'dual_mode';
        }

        return $codes;
    }

    /* ------------------------------ Социалка ------------------------------ */

    private function groupSocial(User $user): array
    {
        $friends = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->count();

        $recommendations = ProfileRecommendation::where('target_id', $user->id)
            ->where('is_hidden', false)
            ->count();

        $referrals = User::where('referred_by', $user->id)->count();

        return [
            ...$this->thresholds($friends, [5 => 'friends_5', 20 => 'friends_20', 50 => 'friends_50']),
            ...$this->thresholds($recommendations, [1 => 'recommended', 5 => 'recommended_5']),
            ...$this->thresholds($referrals, [1 => 'referral_1', 5 => 'referral_5']),
        ];
    }

    /* ------------------------------ Форум ------------------------------ */

    private function groupForum(User $user): array
    {
        $topics = ForumTopic::where('author_id', $user->id)->whereNull('deleted_at');
        $replies = ForumReply::where('author_id', $user->id)->whereNull('deleted_at');

        $likes = (int) (clone $topics)->sum('likes_count') + (int) (clone $replies)->sum('likes_count');

        return [
            ...$this->thresholds((clone $topics)->count(), [1 => 'forum_topic_first']),
            ...$this->thresholds((clone $replies)->count(), [10 => 'forum_replies_10', 50 => 'forum_replies_50']),
            ...$this->thresholds($likes, [25 => 'forum_liked_25']),
        ];
    }

    /* ------------------------------ Чат ------------------------------ */

    private function groupChat(User $user): array
    {
        return $this->thresholds(
            Message::where('user_id', $user->id)->count(),
            [100 => 'chat_100', 1000 => 'chat_1000'],
        );
    }

    /* ------------------------------ Магазин ------------------------------ */

    private function groupShop(User $user): array
    {
        $owned = UserInventory::where('user_id', $user->id)->count();
        $gifted = UserInventory::where('gifted_by', $user->id)->count();

        return [
            ...$this->thresholds($owned, [1 => 'shop_first', 10 => 'shop_collector']),
            ...$this->thresholds((int) $user->apex_coins_spent, [5000 => 'big_spender']),
            ...$this->thresholds($gifted, [1 => 'gift_giver']),
        ];
    }

    /* ------------------------------ Турниры ------------------------------ */

    private function groupTournaments(User $user): array
    {
        return [
            ...$this->thresholds(TournamentParticipant::where('user_id', $user->id)->count(), [1 => 'tournament_join']),
            ...$this->thresholds(TournamentWin::where('user_id', $user->id)->count(), [
                1 => 'tournament_first_win',
                3 => 'tournament_win_3',
            ]),
        ];
    }

    /* ------------------------------ Майнкрафт ------------------------------ */

    private function groupMinecraft(User $user): array
    {
        return $user->minecraft_linked_at ? ['minecraft_linked'] : [];
    }

    /* ------------------------------ Профиль ------------------------------ */

    private function groupProfile(User $user): array
    {
        $codes = [];

        if ($user->avatar && $user->bio && $user->cover_path
            && $user->socials && collect($user->socials)->filter()->count() >= 2) {
            $codes[] = 'profile_complete';
        }

        $days = $user->created_at ? (int) $user->created_at->diffInDays(now()) : 0;

        return [...$codes, ...$this->thresholds($days, [30 => 'account_30d', 365 => 'account_365d'])];
    }

    /* ------------------------------ Рейтинг ------------------------------ */

    private function groupTop(User $user): array
    {
        if ($user->rating_points <= 0 || $user->isStaff()) {
            return [];
        }

        $position = User::query()->excludeStaff()->where('rating_points', '>', $user->rating_points)->count() + 1;

        $codes = [];

        if ($position <= 10) $codes[] = 'top_10';
        if ($position <= 3) $codes[] = 'top_3';
        if ($position === 1) $codes[] = 'top_1';

        return $codes;
    }

    /* ------------------------------ Бридж ------------------------------ */

    private function groupBridge(User $user): array
    {
        $codes = $user->bridge_rank_id ? ['bridge_ranked'] : [];

        $confirmed = UserBridgeTechnique::where('user_id', $user->id)
            ->where('status', UserBridgeTechnique::STATUS_CONFIRMED)
            ->count();

        return [...$codes, ...$this->thresholds($confirmed, [5 => 'bridge_techniques_5'])];
    }

    /**
     * Коды всех порогов, которые значение достигло.
     *
     * @param  array<int, string>  $steps  порог => код
     * @return array<int, string>
     */
    private function thresholds(int|float $value, array $steps): array
    {
        $codes = [];

        foreach ($steps as $min => $code) {
            if ($value >= $min) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
