<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\ClanWar;
use App\Models\ClanWarParticipant;
use App\Models\User;
use App\Services\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\DB;

/**
 * Войны кланов: вызов, принятие, результат, участники.
 *
 * Раньше вся логика жила в контроллере, и вместе с ней — три дыры:
 * decline и leave не проверяли права, complete можно было вызвать повторно
 * (двойные победы), а ничья отдавала победу оппоненту.
 */
class ClanWarService
{
    use AbortsWithMessage;

    private const WAR_RELATIONS = [
        'challenger:id,name,tag,power,banner_color,avatar',
        'opponent:id,name,tag,power,banner_color,avatar',
        'participants.user:id,username,avatar,tier',
        'participants.clan:id,name,tag,banner_color',
    ];

    /** Статусы, в которых война ещё идёт. */
    private const FINISHED_STATUSES = ['completed', 'cancelled', 'declined'];

    /* ----------------------------- Создание ----------------------------- */

    /**
     * Вызвать клан на войну. Нужны права руководства своего клана.
     */
    public function challenge(User $user, Clan $opponent, array $data): ClanWar
    {
        $myClan = $this->myClan($user);

        if ($myClan->id === $opponent->id) {
            $this->abortUnprocessable('Нельзя вызвать свой клан.');
        }

        $this->assertCanManage($myClan, $user);

        return ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $opponent->id,
            'created_by' => $user->id,
            'status' => 'pending',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['challenger', 'opponent']);
    }

    public function accept(User $user, ClanWar $war): ClanWar
    {
        $this->assertOpponentSide($user, $war);
        $this->assertCanManage($this->myClan($user), $user);
        $this->assertPending($war);

        $war->update(['status' => 'accepted']);

        return $war->fresh(self::WAR_RELATIONS);
    }

    public function decline(User $user, ClanWar $war): void
    {
        // Права нужны и здесь: раньше любой участник клана-оппонента
        // мог отклонить войну за руководство.
        $this->assertOpponentSide($user, $war);
        $this->assertCanManage($this->myClan($user), $user);
        $this->assertPending($war);

        $war->update(['status' => 'declined']);
    }

    /* ----------------------------- Результат ----------------------------- */

    /**
     * Внести результат войны: счёт сторон, победитель и статистика кланов.
     */
    public function complete(User $user, ClanWar $war, array $data): ClanWar
    {
        $myClan = $this->myClan($user);

        $this->assertParticipatingClan($myClan, $war);
        $this->assertCanManage($myClan, $user);

        // Повторный отчёт по завершённой войне начислял победы ещё раз
        if (in_array($war->status, self::FINISHED_STATUSES, true)) {
            $this->abortUnprocessable('Война уже завершена.');
        }

        $challengerScore = (int) $data['challenger_score'];
        $opponentScore = (int) $data['opponent_score'];

        DB::transaction(function () use ($war, $data, $challengerScore, $opponentScore) {
            $isDraw = $challengerScore === $opponentScore;

            $winnerId = match (true) {
                $isDraw => null,
                $challengerScore > $opponentScore => $war->challenger_clan_id,
                default => $war->opponent_clan_id,
            };

            $war->update([
                ...$data,
                'status' => 'completed',
                'winner_clan_id' => $winnerId,
                'outcome' => $isDraw ? 'draw' : 'win',
            ]);

            $challenger = Clan::find($war->challenger_clan_id);
            $opponent = Clan::find($war->opponent_clan_id);

            // Ничья не даёт побед никому
            if (! $isDraw) {
                $winner = $winnerId === $challenger->id ? $challenger : $opponent;
                $loser = $winner->id === $challenger->id ? $opponent : $challenger;

                $winner->increment('wins');
                $loser->increment('losses');
            }

            $challenger->recalculatePower();
            $opponent->recalculatePower();

            AchievementService::check($challenger->leader);
            AchievementService::check($opponent->leader);
        });

        return $war->fresh(self::WAR_RELATIONS);
    }

    /* ----------------------------- Участники ----------------------------- */

    public function join(User $user, ClanWar $war): void
    {
        $myClan = $this->myClan($user);

        if (! in_array($myClan->id, [$war->challenger_clan_id, $war->opponent_clan_id], true)) {
            $this->abortForbidden('Вы не участвуете в этой войне.');
        }

        if (in_array($war->status, self::FINISHED_STATUSES, true)) {
            $this->abortUnprocessable('Война уже завершена.');
        }

        $exists = ClanWarParticipant::where('clan_war_id', $war->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            $this->abortUnprocessable('Вы уже участвуете.');
        }

        ClanWarParticipant::create([
            'clan_war_id' => $war->id,
            'user_id' => $user->id,
            'clan_id' => $myClan->id,
        ]);
    }

    /**
     * Выйти из состава участников. Посторонних не пускаем:
     * раньше метод отвечал «ок» вообще всем.
     */
    public function leave(User $user, ClanWar $war): void
    {
        $myClan = $this->myClan($user);

        if (! in_array($myClan->id, [$war->challenger_clan_id, $war->opponent_clan_id], true)) {
            $this->abortForbidden('Вы не участвуете в этой войне.');
        }

        ClanWarParticipant::where('clan_war_id', $war->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /* ----------------------------- Просмотр ----------------------------- */

    public function view(User $user, ClanWar $war): array
    {
        $myClan = $this->myClan($user);

        $this->assertParticipatingClan($myClan, $war);

        $war->load(self::WAR_RELATIONS);

        return [
            'war' => $war,
            'my_clan_id' => $myClan->id,
            'is_participant' => $war->participants->contains('user_id', $user->id),
        ];
    }

    /* ----------------------------- Внутреннее ----------------------------- */

    private function myClan(User $user): Clan
    {
        $clan = $user->clanMember?->clan;

        abort_if(! $clan, 403, 'Вы не в клане.');

        return $clan;
    }

    /**
     * Руководство клана: лидер или офицер.
     */
    private function assertCanManage(Clan $clan, User $user): void
    {
        $can = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['leader', 'officer'])
            ->exists();

        abort_unless($can, 403, 'Нет прав.');
    }

    private function assertPending(ClanWar $war): void
    {
        if ($war->status !== 'pending') {
            $this->abortUnprocessable('Война уже не в статусе ожидания.');
        }
    }

    private function assertOpponentSide(User $user, ClanWar $war): void
    {
        $myClan = $this->myClan($user);

        if ($myClan->id !== $war->opponent_clan_id) {
            $this->abortForbidden('Действие доступно только клану-оппоненту.');
        }
    }

    private function assertParticipatingClan(Clan $clan, ClanWar $war): void
    {
        if (! in_array($clan->id, [$war->challenger_clan_id, $war->opponent_clan_id], true)) {
            $this->abortForbidden('Вы не участвуете в этой войне.');
        }
    }
}
