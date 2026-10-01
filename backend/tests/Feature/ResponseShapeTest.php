<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMember;
use App\Models\TierTest;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Характеризационный тест: фиксирует ТОЧНЫЙ набор полей в ответах
 * тир-тестов и заявок в клан.
 *
 * Нужен перед переводом этих ответов на API Resources: если ресурс
 * добавит или потеряет поле, тест это поймает.
 */
class ResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    public function test_my_tier_tests_shape_is_stable(): void
    {
        $player = User::factory()->create();

        TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1234',
            'preferred_time' => 'сегодня',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($player)
            ->getJson('/api/tier-tests')
            ->assertOk();

        // my_tests — обычный список, без пагинатора
        $test = $response->json('my_tests.0');

        $this->assertIsArray($test);

        // Ключи, на которые опирается фронтенд
        foreach (['id', 'user_id', 'mode', 'status', 'notes', 'result_tier', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $test, "Пропало поле my_tests.*.{$key}");
        }

        $this->assertSame($player->id, $test['user_id']);
    }

    public function test_tester_queue_shape_is_stable(): void
    {
        $tester = User::factory()->tester()->create();
        $player = User::factory()->create();

        TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1234',
            'preferred_time' => 'сегодня',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($tester)
            ->getJson('/api/tester/tier-tests?status=pending')
            ->assertOk();

        $test = $response->json('data.0');

        $this->assertIsArray($test);

        foreach (['id', 'mode', 'status', 'is_priority', 'user', 'tester', 'claimer'] as $key) {
            $this->assertArrayHasKey($key, $test, "Пропало поле data.*.{$key}");
        }

        $this->assertSame($player->id, $test['user']['id']);
    }

    public function test_clan_applications_shape_is_stable(): void
    {
        $leader = User::factory()->create();
        $applicant = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Клан формы',
            'tag' => 'SHP',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
            'message' => 'Возьмите',
        ]);

        $response = $this->actingAs($leader)
            ->getJson("/api/clans/{$clan->id}/applications")
            ->assertOk();

        $application = $response->json('applications.0');

        $this->assertIsArray($application);

        foreach (['id', 'status', 'message', 'user'] as $key) {
            $this->assertArrayHasKey($key, $application, "Пропало поле applications.*.{$key}");
        }

        $this->assertSame($applicant->id, $application['user']['id']);
    }

    public function test_clan_show_shape_is_stable(): void
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Клан показа',
            'tag' => 'SHW',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        $response = $this->getJson("/api/clans/{$clan->id}")->assertOk();

        foreach (['clan', 'is_member', 'my_clan_id', 'application', 'members_count', 'incoming_wars', 'outgoing_wars'] as $key) {
            $this->assertArrayHasKey($key, $response->json(), "Пропало поле {$key} в /clans/{id}");
        }

        $this->assertSame($clan->id, $response->json('clan.id'));
        $this->assertSame(1, $response->json('members_count'));
    }
}
