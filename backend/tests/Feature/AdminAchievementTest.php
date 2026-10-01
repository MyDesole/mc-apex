<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAchievementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function achievement(array $attributes = []): Achievement
    {
        return Achievement::create(array_merge([
            'code' => 'code_' . uniqid(),
            'name' => 'Ачивка',
            'description' => 'Описание',
            'icon' => 'star',
            'color' => '#7c3aed',
            'rarity' => 'common',
            'points' => 10,
            'is_system' => false,
            'is_active' => true,
        ], $attributes));
    }

    /* ------------------------------- index ------------------------------ */

    public function test_index_orders_by_points_and_counts_grants(): void
    {
        $rare = $this->achievement(['name' => 'Дорогая', 'points' => 100]);
        $cheap = $this->achievement(['name' => 'Дешёвая', 'points' => 1]);

        $user = User::factory()->create();
        $user->achievements()->attach($rare->id, ['earned_at' => now()]);
        $user->achievements()->attach($cheap->id, ['earned_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements')
            ->assertOk();

        $this->assertSame(
            [$cheap->id, $rare->id],
            collect($response->json('achievements'))->pluck('id')->all()
        );

        $points = collect($response->json('achievements'))->pluck('granted_count', 'id')->all();
        $this->assertSame(1, $points[$rare->id]);
        $this->assertSame(1, $points[$cheap->id]);
    }

    public function test_index_search_and_rarity_filters(): void
    {
        $wanted = $this->achievement(['name' => 'Легенда тира', 'code' => 'tier_s', 'rarity' => 'legendary']);
        $this->achievement(['name' => 'Другая', 'code' => 'other', 'rarity' => 'common']);

        $byName = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements?search=Легенда')
            ->assertOk();
        $this->assertSame([$wanted->id], collect($byName->json('achievements'))->pluck('id')->all());

        $byCode = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements?search=tier_s')
            ->assertOk();
        $this->assertSame([$wanted->id], collect($byCode->json('achievements'))->pluck('id')->all());

        $byRarity = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements?rarity=legendary')
            ->assertOk();
        $this->assertSame([$wanted->id], collect($byRarity->json('achievements'))->pluck('id')->all());
    }

    public function test_index_system_and_custom_filters(): void
    {
        $system = $this->achievement(['is_system' => true]);
        $custom = $this->achievement(['is_system' => false]);

        $systemOnly = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements?system=1')
            ->assertOk();
        $this->assertSame([$system->id], collect($systemOnly->json('achievements'))->pluck('id')->all());

        $customOnly = $this->actingAs($this->admin())
            ->getJson('/api/admin/achievements?custom=1')
            ->assertOk();
        $this->assertSame([$custom->id], collect($customOnly->json('achievements'))->pluck('id')->all());
    }

    /* ------------------------------- store ------------------------------ */

    public function test_store_creates_custom_achievement_with_generated_code(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/achievements', [
                'name' => 'Новая ачивка',
                'description' => 'За что-то',
                'icon' => 'trophy',
                'color' => '#f97316',
                'rarity' => 'epic',
                'points' => 150,
                'coin_reward' => 500,
            ])
            ->assertCreated();

        $this->assertStringStartsWith('custom_', $response->json('achievement.code'));
        $this->assertFalse($response->json('achievement.is_system'));
        $this->assertTrue($response->json('achievement.is_active'));
        $this->assertSame(500, $response->json('achievement.coin_reward'));

        $this->assertDatabaseHas('achievements', [
            'id' => $response->json('achievement.id'),
            'name' => 'Новая ачивка',
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    public function test_store_accepts_explicit_code_and_rejects_duplicate(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/achievements', [
                'name' => 'С кодом',
                'description' => 'Описание',
                'icon' => 'x',
                'color' => '#000',
                'rarity' => 'rare',
                'points' => 5,
                'code' => 'my_custom_code',
            ])
            ->assertCreated()
            ->assertJsonPath('achievement.code', 'my_custom_code');

        $this->actingAs($admin)
            ->postJson('/api/admin/achievements', [
                'name' => 'Дубль',
                'description' => 'Описание',
                'icon' => 'x',
                'color' => '#000',
                'rarity' => 'rare',
                'points' => 5,
                'code' => 'my_custom_code',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_store_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/achievements', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'icon', 'color', 'rarity', 'points']);

        $this->actingAs($admin)
            ->postJson('/api/admin/achievements', [
                'name' => 'Ачивка',
                'description' => 'Описание',
                'icon' => 'x',
                'color' => '#000',
                'rarity' => 'mythic',
                'points' => 10001,
                'coin_reward' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rarity', 'points', 'coin_reward']);
    }

    /* ------------------------------ update ------------------------------ */

    public function test_update_changes_fields_partially(): void
    {
        $achievement = $this->achievement(['name' => 'Старое', 'points' => 10]);

        $response = $this->actingAs($this->admin())
            ->putJson("/api/admin/achievements/{$achievement->id}", [
                'name' => 'Новое имя',
                'points' => 42,
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertSame('Новое имя', $response->json('achievement.name'));
        $this->assertSame(42, $response->json('achievement.points'));
        $this->assertFalse($response->json('achievement.is_active'));
        // Описание не передавали — осталось прежним
        $this->assertSame('Описание', $response->json('achievement.description'));
    }

    public function test_update_validates_rarity_and_points(): void
    {
        $achievement = $this->achievement();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/achievements/{$achievement->id}", ['rarity' => 'godlike'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rarity');

        $this->actingAs($this->admin())
            ->putJson("/api/admin/achievements/{$achievement->id}", ['points' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('points');
    }

    public function test_system_achievement_cannot_be_disabled_or_deleted(): void
    {
        $admin = $this->admin();
        $system = $this->achievement(['is_system' => true, 'name' => 'Системная']);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/achievements/{$system->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->assertSame('Системную ачивку нельзя отключить.', $response->json('message'));
        $this->assertTrue($system->fresh()->is_active);

        // Включить обратно / переименовать системную можно
        $this->actingAs($admin)
            ->putJson("/api/admin/achievements/{$system->id}", [
                'is_active' => true,
                'name' => 'Системная v2',
            ])
            ->assertOk();

        $this->assertSame('Системная v2', $system->fresh()->name);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/admin/achievements/{$system->id}")
            ->assertStatus(422);

        $this->assertStringContainsString('нельзя удалить', $response->json('message'));
        $this->assertDatabaseHas('achievements', ['id' => $system->id]);
    }

    public function test_destroy_custom_achievement_detaches_users(): void
    {
        $achievement = $this->achievement();
        $user = User::factory()->create();
        $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/achievements/{$achievement->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('achievements', ['id' => $achievement->id]);
        $this->assertDatabaseMissing('user_achievements', ['achievement_id' => $achievement->id]);
    }
}
