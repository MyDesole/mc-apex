<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrettyUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_opens_by_username(): void
    {
        $user = User::factory()->create(['username' => 'NikitaPro']);

        $response = $this->getJson('/api/users/NikitaPro')->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_profile_username_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'NikitaPro']);

        $response = $this->getJson('/api/users/nikitapro')->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_profile_still_opens_by_id(): void
    {
        $user = User::factory()->create(['username' => 'Somebody']);

        // Старые ссылки не должны сломаться
        $this->getJson("/api/users/{$user->id}")->assertOk();
        $this->getJson("/api/players/{$user->id}")->assertOk();

        $this->assertSame(
            $user->id,
            $this->getJson("/api/players/{$user->id}")->json('user.id')
        );
    }

    public function test_profile_returns_404_for_unknown_username(): void
    {
        $this->getJson('/api/users/НетТакогоНика')->assertNotFound();
    }

    public function test_clan_opens_by_name(): void
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Team Apex',
            'tag' => 'APEX',
            'leader_id' => $leader->id,
        ]);

        $response = $this->getJson('/api/clans/' . rawurlencode('Team Apex'))->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_name_with_underscores_matches_spaces(): void
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Team Apex',
            'tag' => 'APEX',
            'leader_id' => $leader->id,
        ]);

        // Ссылка вида /clan/Team_Apex должна открывать «Team Apex»
        $response = $this->getJson('/api/clans/Team_Apex')->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_name_is_case_insensitive(): void
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Team Apex',
            'tag' => 'APEX',
            'leader_id' => $leader->id,
        ]);

        $response = $this->getJson('/api/clans/team_apex')->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_still_opens_by_id(): void
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Team Apex',
            'tag' => 'APEX',
            'leader_id' => $leader->id,
        ]);

        $response = $this->getJson("/api/clans/{$clan->id}")->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_returns_404_for_unknown_name(): void
    {
        $this->getJson('/api/clans/НетТакогоКлана')->assertNotFound();
    }

    public function test_topics_list_route_is_not_shadowed_by_clan_show(): void
    {
        // /clans/top объявлен раньше динамического маршрута — он не должен съедаться
        $response = $this->getJson('/api/clans/top')->assertOk();

        $this->assertIsArray($response->json());
    }
}
