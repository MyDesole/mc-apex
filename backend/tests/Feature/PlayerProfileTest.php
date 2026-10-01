<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Страховочная сетка для профиля игрока.
 * Фиксирует текущее поведение, чтобы рефакторинг не изменил ответы API.
 */
class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    /* ----------------------------- Просмотр ----------------------------- */

    public function test_profile_is_public_and_returns_expected_payload(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['username' => 'Target']);

        $response = $this->actingAs($viewer)
            ->getJson("/api/users/{$player->id}")
            ->assertOk();

        $response->assertJsonStructure([
            'user' => ['id', 'username', 'tier', 'aspects', 'all_achievements'],
            'friendship',
            'rank' => ['position', 'total'],
            'recommendations',
            'my_recommendation',
            'can_recommend',
        ]);

        $this->assertSame($player->id, $response->json('user.id'));
    }

    public function test_profile_is_available_to_guests(): void
    {
        $player = User::factory()->create();

        $response = $this->getJson("/api/users/{$player->id}")->assertOk();

        $this->assertSame($player->id, $response->json('user.id'));

        // Гость не друг и не может рекомендовать
        $this->assertNull($response->json('friendship'));
        $this->assertFalse($response->json('can_recommend'));
    }

    public function test_profile_by_username_and_by_id_agree(): void
    {
        $player = User::factory()->create(['username' => 'ByNick']);

        $byId = $this->getJson("/api/users/{$player->id}")->assertOk();
        $byNick = $this->getJson('/api/users/ByNick')->assertOk();

        $this->assertSame($byId->json('user.id'), $byNick->json('user.id'));
    }

    public function test_profile_returns_404_for_unknown_user(): void
    {
        $this->getJson('/api/users/НетТакогоНика')->assertNotFound();
    }

    public function test_rank_position_is_reported_when_tier_score_positive(): void
    {
        // Соперник с большим счётом — цель должна оказаться ниже
        User::factory()->create(['tier_score' => 500]);
        $player = User::factory()->create(['tier_score' => 100]);

        $response = $this->getJson("/api/users/{$player->id}")->assertOk();

        $this->assertSame(2, $response->json('rank.position'));
        $this->assertGreaterThanOrEqual(2, $response->json('rank.total'));
    }

    public function test_friendship_state_is_reported_to_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create();

        Friendship::create([
            'user_id' => $viewer->id,
            'friend_id' => $player->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($viewer)->getJson("/api/users/{$player->id}")->assertOk();

        $this->assertSame('accepted', $response->json('friendship.status'));
        $this->assertTrue($response->json('friendship.initiated_by_me'));

        // Другу можно оставлять рекомендацию
        $this->assertTrue($response->json('can_recommend'));
    }

    public function test_aspects_are_exposed_for_both_modes(): void
    {
        $player = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $player->id,
            'block_placing' => 10, 'rotka' => 5, 'movement' => 5, 'aim' => 5, 'game_sense' => 5,
        ]);

        PlayerAspectBedwars::create([
            'user_id' => $player->id,
            'pvp' => 8, 'game_sense' => 8, 'bed_play' => 8, 'teamplay' => 8, 'building' => 8,
        ]);

        $response = $this->getJson("/api/users/{$player->id}")->assertOk();

        $this->assertSame(10, $response->json('user.aspects.pvp.block_placing'));
        $this->assertSame(8, $response->json('user.aspects.bedwars.pvp'));
    }

    /* ----------------------------- Свои данные ----------------------------- */

    public function test_guest_cannot_update_profile(): void
    {
        $this->putJson('/api/players/me', ['bio' => 'привет'])->assertUnauthorized();
        $this->putJson('/api/players/me/aspects', ['mode' => 'pvp'])->assertUnauthorized();
        $this->postJson('/api/players/me/avatar/remove')->assertUnauthorized();
    }

    public function test_user_can_update_bio_and_banner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/players/me', [
                'bio' => 'Играю в бедварс',
                'banner_color' => '#123456',
            ])
            ->assertOk();

        $this->assertSame('Играю в бедварс', $response->json('user.bio'));
        $this->assertSame('#123456', $response->json('user.banner_color'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'Играю в бедварс',
        ]);
    }

    public function test_bio_too_long_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me', ['bio' => str_repeat('a', 501)])
            ->assertStatus(422);
    }

    public function test_socials_are_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me', [
                'socials' => [
                    'discord' => 'nick#1234',
                    'youtube' => 'https://youtube.com/@nick',
                ],
            ])
            ->assertOk();

        $socials = $user->fresh()->socials;

        $this->assertSame('nick#1234', $socials['discord']);
        $this->assertSame('https://youtube.com/@nick', $socials['youtube']);
    }

    /* ----------------------------- Файлы ----------------------------- */

    public function test_avatar_upload_replaces_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => 'users/1/old.png']);
        Storage::disk('public')->put('users/1/old.png', 'old');

        $this->actingAs($user)
            ->post('/api/players/me', [
                '_method' => 'PUT',
                'avatar' => UploadedFile::fake()->image('new.png', 200, 200),
            ])
            ->assertOk();

        $fresh = $user->fresh();

        $this->assertNotSame('users/1/old.png', $fresh->avatar);
        Storage::disk('public')->assertMissing('users/1/old.png');
        Storage::disk('public')->assertExists($fresh->avatar);
    }

    public function test_avatar_and_cover_can_be_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar' => 'users/1/a.png',
            'cover_path' => 'users/1/c.png',
        ]);

        Storage::disk('public')->put('users/1/a.png', 'a');
        Storage::disk('public')->put('users/1/c.png', 'c');

        $this->actingAs($user)->postJson('/api/players/me/avatar/remove')->assertOk();
        $this->assertNull($user->fresh()->avatar);

        $this->actingAs($user)->postJson('/api/players/me/cover/remove')->assertOk();
        $this->assertNull($user->fresh()->cover_path);
    }

    /* ----------------------------- Аспекты ----------------------------- */

    public function test_aspects_pvp_mode_updates_record_and_tier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/players/me/aspects', [
                'mode' => 'pvp',
                'block_placing' => 15,
                'rotka' => 10,
                'movement' => 12,
                'aim' => 14,
                'game_sense' => 16,
            ])
            ->assertOk();

        $this->assertSame(15, $response->json('aspect.block_placing'));

        $this->assertDatabaseHas('player_aspects_pvp', [
            'user_id' => $user->id,
            'block_placing' => 15,
            'game_sense' => 16,
        ]);
    }

    public function test_aspects_bedwars_mode_updates_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/players/me/aspects', [
                'mode' => 'bedwars',
                'pvp' => 11,
                'game_sense' => 12,
                'bed_play' => 13,
                'teamplay' => 14,
                'building' => 15,
            ])
            ->assertOk();

        $this->assertSame(11, $response->json('aspect.pvp'));

        $this->assertDatabaseHas('player_aspects_bedwars', [
            'user_id' => $user->id,
            'bed_play' => 13,
        ]);
    }

    public function test_aspects_reject_out_of_range_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me/aspects', [
                'mode' => 'pvp',
                'block_placing' => 21,
                'rotka' => 0,
                'movement' => 0,
                'aim' => 0,
                'game_sense' => 0,
            ])
            ->assertStatus(422);
    }

    public function test_aspects_require_all_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me/aspects', ['mode' => 'pvp', 'block_placing' => 5])
            ->assertStatus(422);
    }

    /* ----------------------------- Расширенный профиль ----------------------------- */

    public function test_extended_profile_fields_are_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me/profile', [
                'status' => 'В поиске клана',
                'quote' => 'Играй красиво',
                'bio' => 'Немного о себе',
                'accent_color' => '#abcdef',
                'profile_visibility' => 'public',
                'favorite_modes' => ['bedwars', 'pvp'],
            ])
            ->assertOk();

        $fresh = $user->fresh();

        $this->assertSame('В поиске клана', $fresh->status);
        $this->assertSame('Играй красиво', $fresh->quote);
        $this->assertSame('#abcdef', $fresh->accent_color);
    }

    public function test_profile_visibility_must_be_known_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/players/me/profile', ['profile_visibility' => 'суперсекретно'])
            ->assertStatus(422);
    }

    public function test_card_background_upload_and_removal(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/players/me/profile', [
                '_method' => 'PUT',
                'card_background' => UploadedFile::fake()->image('bg.png', 800, 400),
            ])
            ->assertOk();

        $stored = $user->fresh()->card_background;

        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);

        $this->actingAs($user)->postJson('/api/players/me/card-background/remove')->assertOk();

        $this->assertNull($user->fresh()->card_background);
        Storage::disk('public')->assertMissing($stored);
    }
}
