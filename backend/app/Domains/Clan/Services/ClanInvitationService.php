<?php

namespace App\Domains\Clan\Services;

use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanApplication;
use App\Domains\Clan\Models\ClanInvitation;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Notifications\ClanInvitationNotification;
use App\Domains\Clan\Support\ClanLevel;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Приглашения в клан: руководство зовёт игрока по нику, игрок принимает или отказывается.
 */
class ClanInvitationService
{
    use AbortsWithMessage;

    /** Не больше стольких висящих приглашений у клана: защита от рассылки всем подряд. */
    public const MAX_PENDING = 20;

    public function invite(Clan $clan, User $actor, string $username, ?string $message): ClanInvitation
    {
        $target = User::query()->whereRaw('lower(username) = ?', [mb_strtolower(trim($username))])->first();

        if (! $target) {
            $this->invalid('username', 'Игрок с таким ником не найден.');
        }

        if ($target->is_banned) {
            $this->invalid('username', 'Этого игрока нельзя пригласить.');
        }

        if ($target->id === $actor->id) {
            $this->invalid('username', 'Нельзя пригласить самого себя.');
        }

        if ($target->clanMember) {
            $this->invalid('username', 'Игрок уже состоит в клане.');
        }

        if ($clan->members()->count() >= ClanLevel::memberCap($clan)) {
            $this->abortUnprocessable('Клан заполнен: свободных мест нет.');
        }

        $this->expireOld($clan);

        $existing = ClanInvitation::where('clan_id', $clan->id)
            ->where('user_id', $target->id)
            ->where('status', ClanInvitation::STATUS_PENDING)
            ->first();

        if ($existing) {
            $this->invalid('username', 'Этому игроку приглашение уже отправлено.');
        }

        if (ClanInvitation::where('clan_id', $clan->id)->where('status', ClanInvitation::STATUS_PENDING)->count() >= self::MAX_PENDING) {
            $this->abortUnprocessable('Слишком много неотвеченных приглашений. Отмените ненужные или дождитесь ответов.');
        }

        $invitation = ClanInvitation::create([
            'clan_id' => $clan->id,
            'user_id' => $target->id,
            'invited_by' => $actor->id,
            'message' => $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 200) : null,
            'status' => ClanInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(ClanInvitation::TTL_DAYS),
        ]);

        $target->notify(new ClanInvitationNotification($clan, $invitation, $actor->username));

        return $invitation->load('user:id,username,avatar,tier');
    }

    /** Висящие приглашения клана (для руководства). */
    public function forClan(Clan $clan): Collection
    {
        $this->expireOld($clan);

        return ClanInvitation::where('clan_id', $clan->id)
            ->where('status', ClanInvitation::STATUS_PENDING)
            ->with('user:id,username,avatar,tier', 'inviter:id,username')
            ->latest('id')
            ->get();
    }

    public function cancel(Clan $clan, ClanInvitation $invitation): void
    {
        abort_if($invitation->clan_id !== $clan->id, 404);

        if ($invitation->status === ClanInvitation::STATUS_PENDING) {
            $invitation->update(['status' => ClanInvitation::STATUS_CANCELLED, 'responded_at' => now()]);
        }
    }

    /** Приглашения игрока: действующие, с данными клана. */
    public function forUser(User $user): Collection
    {
        return ClanInvitation::where('user_id', $user->id)
            ->where('status', ClanInvitation::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['clan' => fn ($q) => $q->withCount('members'), 'inviter:id,username'])
            ->latest('id')
            ->get();
    }

    public function accept(ClanInvitation $invitation, User $user): Clan
    {
        $this->assertAnswerable($invitation, $user);

        $clan = $invitation->clan;

        if ($user->clanMember) {
            $invitation->update(['status' => ClanInvitation::STATUS_CANCELLED, 'responded_at' => now()]);
            $this->abortUnprocessable('Вы уже состоите в клане.');
        }

        if ($clan->members()->count() >= ClanLevel::memberCap($clan)) {
            $this->abortUnprocessable('В клане уже нет свободных мест.');
        }

        DB::transaction(function () use ($invitation, $user, $clan) {
            // Под блокировкой: игрок мог одновременно вступить в другой клан
            if (ClanMember::where('user_id', $user->id)->lockForUpdate()->exists()) {
                abort(409, 'Вы уже состоите в клане.');
            }

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $user->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            $user->update(['clan_joined_at' => now()]);
            $invitation->update(['status' => ClanInvitation::STATUS_ACCEPTED, 'responded_at' => now()]);
            $clan->recalculatePower();
        });

        // Остальные приглашения и заявки игрока больше не нужны
        ClanInvitation::where('user_id', $user->id)
            ->where('id', '!=', $invitation->id)
            ->where('status', ClanInvitation::STATUS_PENDING)
            ->update(['status' => ClanInvitation::STATUS_CANCELLED, 'responded_at' => now()]);

        ClanApplication::where('user_id', $user->id)->where('status', 'pending')->update(['status' => 'declined']);

        AchievementService::check($user);

        // Сообщаем тому, кто позвал, и лидеру
        $notify = collect([$invitation->inviter, $clan->leader])->filter()->unique('id');
        foreach ($notify as $person) {
            $person->notify(new \App\Domains\Clan\Notifications\ClanInvitationAcceptedNotification($clan, $user->username));
        }

        return $clan->fresh();
    }

    public function decline(ClanInvitation $invitation, User $user): void
    {
        $this->assertAnswerable($invitation, $user);

        $invitation->update(['status' => ClanInvitation::STATUS_DECLINED, 'responded_at' => now()]);
    }

    private function assertAnswerable(ClanInvitation $invitation, User $user): void
    {
        abort_if($invitation->user_id !== $user->id, 403, 'Это приглашение адресовано не вам.');

        if (! $invitation->isActive()) {
            $this->abortUnprocessable('Приглашение уже недействительно.');
        }
    }

    private function expireOld(Clan $clan): void
    {
        ClanInvitation::where('clan_id', $clan->id)
            ->where('status', ClanInvitation::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => ClanInvitation::STATUS_CANCELLED]);
    }
}
