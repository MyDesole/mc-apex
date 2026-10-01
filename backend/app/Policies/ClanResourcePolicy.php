<?php

namespace App\Policies;

use App\Models\ClanResource;
use App\Models\User;

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
