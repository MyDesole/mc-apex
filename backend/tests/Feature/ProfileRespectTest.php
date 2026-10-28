<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Респекты, гости профиля и топ недели.
 */
class ProfileRespectTest extends TestCase
{
    use RefreshDatabase;

    public function test_respect_once_a_day_guests_and_top(): void
    {
        $owner = User::factory()->create();
        $fan = User::factory()->create();
        $other = User::factory()->create();

        // Себе — нельзя
        $this->actingAs($owner)->postJson("/api/players/{$owner->id}/respects")->assertStatus(422);

        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/respects")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('given_today', true)
            ->assertJsonPath('rank', 1)
            ->assertJsonPath('recent.0.username', $fan->username);

        // Второй раз за день — нельзя
        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/respects")->assertStatus(422);

        $this->actingAs($other)->postJson("/api/players/{$other->id}/visit")->assertOk();
        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/visit")->assertOk();
        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/visit")->assertOk();
        $this->actingAs($other)->postJson("/api/players/{$owner->id}/visit")->assertOk();

        $this->actingAs($owner)->getJson('/api/players/me/guests')
            ->assertOk()
            ->assertJsonPath('week_unique', 2)
            ->assertJsonCount(2, 'guests');

        // Свой профиль в гостях не считается
        $this->actingAs($other)->getJson('/api/players/me/guests')->assertJsonPath('week_unique', 0);

        $this->getJson('/api/respects/top')->assertOk()->assertJsonPath('players.0.id', $owner->id)->assertJsonPath('players.0.respects', 1);
    }

    public function test_respect_can_be_revoked_but_not_regiven_the_same_day(): void
    {
        $owner = User::factory()->create();
        $fan = User::factory()->create(['tier' => 'E', 'tier_score' => 0]);

        // Забирать нечего
        $this->actingAs($fan)->deleteJson("/api/players/{$owner->id}/respects")->assertStatus(422);

        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/respects")
            ->assertOk()
            ->assertJsonPath('total', 1)
            // Новичок без тир-теста — без тира, хотя в базе E
            ->assertJsonPath('recent.0.tier', null);

        $this->actingAs($fan)->deleteJson("/api/players/{$owner->id}/respects")
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('given_today', false)
            ->assertJsonPath('revoked_today', true)
            ->assertJsonCount(0, 'recent');

        // Забранный в топ не идёт, а снова дать сегодня нельзя
        $this->getJson('/api/respects/top')->assertJsonCount(0, 'players');
        $this->actingAs($fan)->postJson("/api/players/{$owner->id}/respects")->assertStatus(422);
    }
}
