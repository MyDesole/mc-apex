<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Резолв сущностей из URL: число — это id, строка — ник или имя.
 *
 * Две копии этой логики (игрок и клан) объединены в trait, поэтому
 * важно зафиксировать полное поведение: регистр, подчёркивания,
 * пробелы и 404 на неизвестное значение.
 */
class UrlResolutionTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------- Игроки ------------------------------- */

    public function test_player_resolves_by_id(): void
    {
        $user = User::factory()->create(['username' => 'ByNumericId']);

        $response = $this->getJson("/api/users/{$user->id}")->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_player_resolves_by_username(): void
    {
        $user = User::factory()->create(['username' => 'ExactNick']);

        $response = $this->getJson('/api/users/ExactNick')->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_player_username_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'MixedCase']);

        $response = $this->getJson('/api/users/mixedcase')->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_player_username_url_is_decoded(): void
    {
        $user = User::factory()->create(['username' => 'With Space']);

        $response = $this->getJson('/api/users/' . rawurlencode('With Space'))->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_unknown_player_gives_404(): void
    {
        $this->getJson('/api/users/НетТакогоНика')->assertNotFound();
    }

    public function test_unknown_player_id_gives_404(): void
    {
        $this->getJson('/api/users/999999')->assertNotFound();
    }

    /* -------------------------------- Кланы -------------------------------- */

    public function test_clan_resolves_by_id(): void
    {
        $clan = Clan::create([
            'name' => 'By Numeric',
            'tag' => 'NUM',
            'leader_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/clans/{$clan->id}")->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_resolves_by_name(): void
    {
        $clan = Clan::create([
            'name' => 'Plain Name',
            'tag' => 'PLN',
            'leader_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/clans/' . rawurlencode('Plain Name'))->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_name_with_underscores_matches_spaces(): void
    {
        $clan = Clan::create([
            'name' => 'Team Apex',
            'tag' => 'APX',
            'leader_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/clans/Team_Apex')->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_name_with_spaces_matches_underscored_storage(): void
    {
        $clan = Clan::create([
            'name' => 'Under_Score',
            'tag' => 'UND',
            'leader_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/clans/' . rawurlencode('Under Score'))->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_clan_name_is_case_insensitive(): void
    {
        $clan = Clan::create([
            'name' => 'Case Clan',
            'tag' => 'CSE',
            'leader_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/clans/case_clan')->assertOk();

        $this->assertSame($clan->id, $response->json('clan.id'));
    }

    public function test_unknown_clan_gives_404(): void
    {
        $this->getJson('/api/clans/НетТакогоКлана')->assertNotFound();
    }

    public function test_unknown_clan_id_gives_404(): void
    {
        $this->getJson('/api/clans/999999')->assertNotFound();
    }

    /* --------------------------- Публичные алиасы --------------------------- */

    public function test_user_alias_works_like_players_route(): void
    {
        $user = User::factory()->create(['username' => 'AliasNick']);

        $byId = $this->getJson("/api/players/{$user->id}")->assertOk();
        $byAlias = $this->getJson('/api/users/AliasNick')->assertOk();

        $this->assertSame($byId->json('user.id'), $byAlias->json('user.id'));
    }
}
