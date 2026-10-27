<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Питомец профиля: действия раз в день, опыт, настроение, имя.
 */
class ProfilePetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_pet_feed_and_play_once_a_day(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['profile_pet' => 'fox'])->save();
        $guest = User::factory()->create();

        // Голодный, пока никто не кормил
        $this->getJson("/api/players/{$owner->id}/pet")->assertOk()->assertJsonPath('mood', 'hungry');

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertOk();
        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertStatus(422);

        $stats = $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/feed")
            ->assertOk()
            ->json();

        // 1 за ласку + 2 за еду; накормлен — теперь радостный
        $this->assertSame(3, $stats['xp']);
        $this->assertSame('happy', $stats['mood']);
        $this->assertEqualsCanonicalizing(['pet', 'feed'], $stats['done_today']);
        $this->assertSame($guest->id, $stats['friends'][0]->id ?? $stats['friends'][0]['id']);

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/dance")->assertNotFound();
    }

    public function test_pet_without_pet_and_rename(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertStatus(422);

        $this->actingAs($owner)->putJson('/api/players/me/pet-name', ['name' => '<b>Барсик</b>'])
            ->assertOk()
            ->assertJsonPath('name', 'Барсик');
    }
}
