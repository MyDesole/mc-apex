<?php

namespace App\Policies;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;

/**
 * Права в клане.
 *
 * Управлять профилем клана может лидер; заявки рассматривает руководство
 * (лидер или офицер); кикать — только лидер.
 */
class ClanPolicy
{
    public function update(User $user, Clan $clan): bool
    {
        return $clan->isLeader($user->id);
    }

    public function removeMedia(User $user, Clan $clan): bool
    {
        return $clan->isLeader($user->id);
    }

    public function reviewApplications(User $user, Clan $clan): bool
    {
        return $this->isStaff($user, $clan);
    }

    public function kick(User $user, Clan $clan): bool
    {
        return $clan->isLeader($user->id);
    }

    /**
     * Менять роли и передавать лидерство может только лидер.
     */
    public function manageRoles(User $user, Clan $clan): bool
    {
        return $clan->isLeader($user->id);
    }

    /**
     * Создавать события клана может руководство (лидер или офицер).
     */
    public function createEvent(User $user, Clan $clan): bool
    {
        return $this->isStaff($user, $clan);
    }

    /**
     * Удалять событие может автор, лидер или офицер.
     */
    public function deleteEvent(User $user, Clan $clan, int $authorId): bool
    {
        return $authorId === $user->id || $this->isStaff($user, $clan);
    }

    private function isStaff(User $user, Clan $clan): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['leader', 'officer'])
            ->exists();
    }
}
