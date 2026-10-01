<?php

namespace Tests\Feature;

use App\Models\PlayerAspectPvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaRoleTest extends TestCase
{
    use RefreshDatabase;

    private function withAspects(User $user, int $score = 20): User
    {
        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => $score,
            'rotka' => 0,
            'movement' => 0,
            'aim' => 0,
            'game_sense' => 0,
        ]);

        return $user;
    }

    public function test_admin_can_assign_media_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$player->id}/role", ['role' => 'media'])
            ->assertOk();

        $this->assertSame('media', $player->fresh()->role);
        $this->assertTrue($player->fresh()->isMedia());
    }

    public function test_media_is_not_staff_and_participates_in_ranking(): void
    {
        $media = $this->withAspects(User::factory()->create(['role' => 'media']), 20);
        $player = $this->withAspects(User::factory()->create(['role' => 'user']), 10);
        $admin = $this->withAspects(User::factory()->create(['role' => 'admin']), 30);

        // Медийка не персонал
        $this->assertFalse($media->fresh()->isStaff());
        $this->assertFalse($media->fresh()->isModerator());

        $ranking = $this->getJson('/api/ranking')->assertOk();
        $ids = collect($ranking->json('data'))->pluck('id')->all();

        // Участвует в рейтинге
        $this->assertContains($media->id, $ids);

        // Персонал — нет
        $this->assertNotContains($admin->id, $ids);

        // Обычный игрок тоже участвует
        $this->assertContains($player->id, $ids);
    }

    public function test_media_appears_on_home_top(): void
    {
        $media = User::factory()->create(['role' => 'media', 'tier_score' => 80]);
        $admin = User::factory()->create(['role' => 'admin', 'tier_score' => 90]);

        $home = $this->getJson('/api/home')->assertOk();

        $players = collect($home->json('players') ?? [])->pluck('id')->all();

        $this->assertContains($media->id, $players);
        $this->assertNotContains($admin->id, $players);
    }

    public function test_media_label_is_human_readable(): void
    {
        $media = User::factory()->create(['role' => 'media']);

        $this->assertSame('Медийка', $media->roleLabel());
        $this->assertSame('Медийка', $media->role_badge);
    }

    public function test_staff_roles_stay_out_of_ranking(): void
    {
        $tester = $this->withAspects(User::factory()->create(['role' => 'tester']), 30);
        $moderator = $this->withAspects(User::factory()->create(['role' => 'moderator']), 30);
        $media = $this->withAspects(User::factory()->create(['role' => 'media']), 30);

        $ranking = $this->getJson('/api/ranking')->assertOk();
        $ids = collect($ranking->json('data'))->pluck('id')->all();

        $this->assertNotContains($tester->id, $ids);
        $this->assertNotContains($moderator->id, $ids);
        $this->assertContains($media->id, $ids);
    }

    public function test_media_role_is_returned_in_forum_author_payload(): void
    {
        $media = User::factory()->create(['role' => 'media']);

        $category = \App\Models\ForumCategory::create([
            'name' => 'Общее тест',
            'slug' => 'general-test',
            'post_policy' => 'all',
            'is_active' => true,
        ]);

        $topic = \App\Models\ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $media->id,
            'title' => 'Тема от медийки',
            'body' => 'Тело темы',
        ]);

        $response = $this->getJson("/api/forum/topics/{$topic->id}")->assertOk();

        $this->assertTrue($response->json('topic.author.is_media'));
        $this->assertSame('media', $response->json('topic.author.role'));
    }
}
