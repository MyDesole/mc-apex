<?php

namespace Tests\Feature;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /* ------------------------------ Список ------------------------------ */

    public function test_index_lists_users_with_pagination(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin())->getJson('/api/admin/users')->assertOk();

        $this->assertSame(30, $response->json('per_page'));
        $this->assertCount(4, $response->json('data')); // 3 игрока + сам админ
        $this->assertArrayHasKey('clan_member', $response->json('data.0'));
        $this->assertArrayHasKey('achievements_count', $response->json('data.0'));
    }

    public function test_index_sorts_by_created_at_desc(): void
    {
        $old = User::factory()->create();
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $fresh = User::factory()->create();

        $response = $this->actingAs($this->admin())->getJson('/api/admin/users')->assertOk();

        $this->assertSame($fresh->id, $response->json('data.0.id'));
    }

    public function test_index_search_matches_username_and_email(): void
    {
        $byName = User::factory()->create(['username' => 'findme']);
        $byEmail = User::factory()->create(['email' => 'findme@example.com']);
        User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/users?search=findme')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($byName->id, $ids);
        $this->assertContains($byEmail->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_index_role_filter(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        User::factory()->count(2)->create(['role' => 'user']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/users?role=moderator')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$moderator->id], $ids);
    }

    public function test_index_banned_filter(): void
    {
        $banned = User::factory()->create(['is_banned' => true]);
        User::factory()->count(2)->create(['is_banned' => false]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/users?banned=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        // Сам админ не забанен, поэтому в выдаче только забаненный
        $this->assertSame([$banned->id], $ids);
    }

    public function test_index_has_no_verified_filter(): void
    {
        // Документируем текущее поведение: параметр verified в index не поддержан
        // (для верификации есть отдельный /api/admin/users/verified).
        $verified = User::factory()->create(['is_verified' => true]);
        $plain = User::factory()->create(['is_verified' => false]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/users?verified=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($verified->id, $ids);
        $this->assertContains($plain->id, $ids);
    }

    /* ------------------------------- Show ------------------------------- */

    public function test_show_returns_user_with_aspects_and_points(): void
    {
        $user = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => 10, 'rotka' => 1, 'movement' => 2, 'aim' => 3, 'game_sense' => 4,
        ]);

        PlayerAspectBedwars::create([
            'user_id' => $user->id,
            'pvp' => 5, 'game_sense' => 6, 'bed_play' => 7, 'teamplay' => 8, 'building' => 9,
        ]);

        $achievement = Achievement::create([
            'code' => 'test_points', 'name' => 'Тест', 'description' => 'Тест',
            'icon' => 'x', 'color' => '#000', 'rarity' => 'common', 'points' => 42,
        ]);
        $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/users/{$user->id}")
            ->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertSame(10, $response->json('user.aspects.pvp.block_placing'));
        $this->assertSame(9, $response->json('user.aspects.bedwars.building'));
        $this->assertSame(42, $response->json('achievement_points'));
    }

    public function test_show_returns_404_for_unknown_user(): void
    {
        $this->actingAs($this->admin())->getJson('/api/admin/users/999999')->assertNotFound();
    }

    /* ---------------------------- Верификация --------------------------- */

    public function test_verify_and_unverify_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['is_verified' => false]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/verify", ['reason' => 'Известный игрок'])
            ->assertOk();

        $this->assertTrue($response->json('user.is_verified'));
        $this->assertSame('Известный игрок', $response->json('user.verified_reason'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_verified' => true,
            'verified_reason' => 'Известный игрок',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/unverify")
            ->assertOk();

        $this->assertFalse($response->json('user.is_verified'));
        $this->assertNull($response->json('user.verified_reason'));
    }

    public function test_verify_without_reason_clears_it(): void
    {
        $user = User::factory()->create([
            'is_verified' => true,
            'verified_reason' => 'старое',
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/verify")
            ->assertOk();

        $this->assertTrue($response->json('user.is_verified'));
        $this->assertNull($response->json('user.verified_reason'));
    }

    public function test_verify_rejects_too_long_reason(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/verify", ['reason' => str_repeat('a', 129)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_unverify_already_unverified_user_returns_422(): void
    {
        $user = User::factory()->create(['is_verified' => false]);

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/unverify")
            ->assertStatus(422);

        $this->assertSame('Уже не верифицирован.', $response->json('message'));
    }

    public function test_verified_endpoint_filters_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
        $verified = User::factory()->create(['username' => 'verifiedguy', 'is_verified' => true]);
        $plain = User::factory()->create(['username' => 'plainguy', 'is_verified' => false]);

        $all = $this->actingAs($admin)
            ->getJson('/api/admin/users/verified')
            ->assertOk();

        $ids = collect($all->json('data'))->pluck('id')->all();
        $this->assertContains($verified->id, $ids);
        $this->assertContains($plain->id, $ids);

        // Верифицированные идут первыми
        $this->assertTrue($all->json('data.0.is_verified'));

        $onlyVerified = $this->actingAs($admin)
            ->getJson('/api/admin/users/verified?status=verified')
            ->assertOk();
        $verifiedIds = collect($onlyVerified->json('data'))->pluck('id')->all();

        $this->assertContains($verified->id, $verifiedIds);
        $this->assertNotContains($plain->id, $verifiedIds);

        $onlyPlain = $this->actingAs($admin)
            ->getJson('/api/admin/users/verified?status=unverified')
            ->assertOk();
        $plainIds = collect($onlyPlain->json('data'))->pluck('id')->all();

        $this->assertSame([$plain->id], $plainIds);

        $search = $this->actingAs($admin)
            ->getJson('/api/admin/users/verified?search=verifiedguy')
            ->assertOk();
        $this->assertSame(
            [$verified->id],
            collect($search->json('data'))->pluck('id')->all()
        );
    }

    /* -------------------------------- Бан ------------------------------- */

    public function test_ban_requires_reason_and_sets_fields(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/ban")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/ban", [
                'reason' => 'Читы',
                'until' => now()->addDays(7)->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertTrue($response->json('user.is_banned'));
        $this->assertSame('Читы', $response->json('user.ban_reason'));
        $this->assertSame($admin->id, $response->json('user.banned_by'));
        $this->assertNotNull($response->json('user.banned_until'));

        $this->assertTrue($user->fresh()->isBanned());
    }

    public function test_ban_without_until_is_permanent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/ban", ['reason' => 'Навсегда'])
            ->assertOk();

        $this->assertNull($user->fresh()->banned_until);
        $this->assertTrue($user->fresh()->isBanned());
    }

    public function test_ban_rejects_past_until(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/ban", [
                'reason' => 'Прошлое',
                'until' => now()->subDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('until');
    }

    public function test_admin_cannot_ban_self_or_another_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$admin->id}/ban", ['reason' => 'сам себя'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$otherAdmin->id}/ban", ['reason' => 'другого админа'])
            ->assertStatus(422);

        $this->assertFalse($admin->fresh()->is_banned);
        $this->assertFalse($otherAdmin->fresh()->is_banned);
    }

    public function test_unban_clears_all_ban_fields(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'is_banned' => true,
            'ban_reason' => 'Читы',
            'banned_until' => now()->addDay(),
            'banned_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/unban")
            ->assertOk();

        $this->assertFalse($response->json('user.is_banned'));
        $this->assertNull($response->json('user.ban_reason'));
        $this->assertNull($response->json('user.banned_until'));
        $this->assertNull($response->json('user.banned_by'));
    }

    public function test_unban_is_idempotent_and_has_no_guard(): void
    {
        // Документируем асимметрию: unverify для неверифицированного — 422,
        // а unban для незабаненного молча возвращает 200.
        $user = User::factory()->create(['is_banned' => false]);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/unban")
            ->assertOk()
            ->assertJsonPath('user.is_banned', false);
    }

    /* -------------------------------- Роли ------------------------------ */

    public function test_admin_can_change_roles(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        foreach (['user', 'media', 'tester', 'moderator', 'admin', 'user'] as $role) {
            $response = $this->actingAs($admin)
                ->postJson("/api/admin/users/{$user->id}/role", ['role' => $role])
                ->assertOk();

            $this->assertSame($role, $response->json('user.role'));
        }

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_role_change_validates_role_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/role", ['role' => 'superadmin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/role")
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertStatus(422);

        $this->assertSame('Нельзя менять свою роль.', $response->json('message'));
        $this->assertSame('admin', $admin->fresh()->role);
    }

    /* ------------------------------ Ачивки ------------------------------ */

    public function test_grant_and_revoke_achievement(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $achievement = Achievement::create([
            'code' => 'manual_grant', 'name' => 'Выдана вручную', 'description' => 'Описание',
            'icon' => 'x', 'color' => '#111', 'rarity' => 'rare', 'points' => 30,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/achievements/{$achievement->id}")
            ->assertOk();

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        // Повторная выдача — 422
        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/achievements/{$achievement->id}")
            ->assertStatus(422);

        $this->assertSame('Уже есть.', $response->json('message'));

        // Отзыв
        $this->actingAs($admin)
            ->deleteJson("/api/admin/users/{$user->id}/achievements/{$achievement->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /* ------------------------------ Аспекты ----------------------------- */

    public function test_admin_updates_pvp_aspects_and_recalcs_tier(): void
    {
        $user = User::factory()->create(['tier' => 'E', 'tier_score' => 0]);

        $response = $this->actingAs($this->admin())
            ->putJson("/api/admin/users/{$user->id}/aspects", [
                'mode' => 'pvp',
                'block_placing' => 20,
                'rotka' => 20,
                'movement' => 20,
                'aim' => 20,
                'game_sense' => 20,
            ])
            ->assertOk();

        $this->assertDatabaseHas('player_aspects_pvp', [
            'user_id' => $user->id,
            'block_placing' => 20,
        ]);

        $this->assertSame('A', $response->json('user.tier'));
        $this->assertSame(100.0, (float) $response->json('user.tier_score'));
    }

    public function test_admin_updates_bedwars_aspects_with_field_mapping(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/users/{$user->id}/aspects", [
                'mode' => 'bedwars',
                'block_placing' => 1, // → pvp
                'rotka' => 2,         // → bed_play
                'movement' => 3,      // → teamplay
                'aim' => 4,           // → building
                'game_sense' => 5,
            ])
            ->assertOk();

        $this->assertDatabaseHas('player_aspects_bedwars', [
            'user_id' => $user->id,
            'pvp' => 1,
            'bed_play' => 2,
            'teamplay' => 3,
            'building' => 4,
            'game_sense' => 5,
        ]);
    }

    public function test_aspects_validation(): void
    {
        $user = User::factory()->create();

        // Неверный режим
        $this->actingAs($this->admin())
            ->putJson("/api/admin/users/{$user->id}/aspects", [
                'mode' => 'skywars',
                'block_placing' => 1, 'rotka' => 1, 'movement' => 1, 'aim' => 1, 'game_sense' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');

        // Нет обязательного поля
        $this->actingAs($this->admin())
            ->putJson("/api/admin/users/{$user->id}/aspects", [
                'mode' => 'pvp',
                'block_placing' => 1, 'rotka' => 1, 'movement' => 1, 'aim' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('game_sense');

        // Значение больше 20
        $this->actingAs($this->admin())
            ->putJson("/api/admin/users/{$user->id}/aspects", [
                'mode' => 'pvp',
                'block_placing' => 21, 'rotka' => 1, 'movement' => 1, 'aim' => 1, 'game_sense' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('block_placing');

        $this->assertDatabaseCount('player_aspects_pvp', 0);
    }

    /* ----------------------------- Тир-тесты ---------------------------- */

    public function test_conduct_tier_test_creates_completed_test_and_sets_tier(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['tier' => 'E']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/tier-test", [
                'mode' => 'pvp',
                'aspects' => [
                    'block_placing' => 20,
                    'rotka' => 20,
                    'movement' => 20,
                    'aim' => 20,
                    'game_sense' => 20,
                ],
                'notes' => 'Чисто сыграл',
            ])
            ->assertCreated();

        $this->assertSame('A', $response->json('user.tier'));

        $test = TierTest::where('user_id', $user->id)->firstOrFail();

        $this->assertSame($admin->id, $test->tester_id);
        $this->assertSame('pvp', $test->mode);
        $this->assertSame('completed', $test->status);
        $this->assertSame('A', $test->result_tier);
        $this->assertSame('100.00', (string) $test->result_score);
        $this->assertSame(20, $test->aspects['block_placing']);
        $this->assertSame('Чисто сыграл', $test->notes);
        $this->assertNotNull($test->completed_at);
    }

    public function test_tier_test_score_to_tier_boundaries(): void
    {
        $admin = $this->admin();

        // Сумма аспектов = 71 → A, 70 → B, 20 → E (порогов без удвоения)
        $cases = [
            71 => 'A',
            70 => 'B',
            56 => 'B',
            55 => 'C',
            41 => 'C',
            40 => 'D',
            21 => 'D',
            20 => 'E',
        ];

        foreach ($cases as $sum => $expectedTier) {
            $user = User::factory()->create(['tier' => 'E']);

            // Раскладываем сумму по пяти аспектам
            $rest = $sum;
            $aspects = [];
            foreach (['block_placing', 'rotka', 'movement', 'aim', 'game_sense'] as $key) {
                $value = min(20, $rest);
                $aspects[$key] = $value;
                $rest -= $value;
            }

            $this->actingAs($admin)
                ->postJson("/api/admin/users/{$user->id}/tier-test", [
                    'mode' => 'pvp',
                    'aspects' => $aspects,
                ])
                ->assertCreated();

            $this->assertSame(
                $expectedTier,
                TierTest::where('user_id', $user->id)->value('result_tier'),
                "Сумма {$sum} должна давать тир {$expectedTier}"
            );
        }
    }

    public function test_tier_test_validates_mode_specific_aspects(): void
    {
        $user = User::factory()->create();

        // Для bedwars нужен aspects.pvp, а не block_placing
        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/tier-test", [
                'mode' => 'bedwars',
                'aspects' => [
                    'block_placing' => 1, 'rotka' => 1, 'movement' => 1, 'aim' => 1, 'game_sense' => 1,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aspects.pvp', 'aspects.bed_play', 'aspects.teamplay', 'aspects.building']);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/tier-test", ['mode' => 'nope', 'aspects' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');
    }

    public function test_conduct_bedwars_tier_test_stores_aspects(): void
    {
        $user = User::factory()->create(['tier' => 'E']);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$user->id}/tier-test", [
                'mode' => 'bedwars',
                'aspects' => [
                    'pvp' => 1, 'game_sense' => 2, 'bed_play' => 3, 'teamplay' => 4, 'building' => 5,
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('player_aspects_bedwars', [
            'user_id' => $user->id,
            'pvp' => 1,
            'game_sense' => 2,
            'bed_play' => 3,
            'teamplay' => 4,
            'building' => 5,
        ]);

        // Сумма 15 → E
        $this->assertSame('E', TierTest::where('user_id', $user->id)->value('result_tier'));
    }
}
