<?php

namespace App\Domains\Clan\Policies;

use App\Domains\Clan\Models\ClanResource;
use App\Domains\Users\Models\User;

/**
 * Ресурс клана: удалить может автор или лидер клана.
 */
class ClanResourcePolicy
{
    public function delete(User $user, ClanResource $resource): bool
    {
        if ($resource->author_id === $user->id) {
            return true;
        }

        return $user->clanMember?->role === 'leader';
    }
}
