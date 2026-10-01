<?php

namespace Tests\Feature\Concerns;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Общие фабрики для тестов кланового домена.
 *
 * Клан-фабрики в проекте нет, а имена и теги кланов уникальны,
 * поэтому значения генерируются случайно.
 */
trait ClanFixtures
{
    protected function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Клан вместе с записью лидера в clan_members.
     */
    protected function clan(?User $leader = null, array $attributes = []): Clan
    {
        $leader ??= $this->user();

        $clan = Clan::create(array_merge([
            'name' => 'Clan '.Str::random(8),
            'tag' => strtoupper(Str::random(6)),
            'leader_id' => $leader->id,
        ], $attributes));

        $this->member($clan, $leader, 'leader');

        return $clan;
    }

    protected function member(Clan $clan, User $user, string $role = 'member', array $attributes = []): ClanMember
    {
        return ClanMember::create(array_merge([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ], $attributes));
    }
}
