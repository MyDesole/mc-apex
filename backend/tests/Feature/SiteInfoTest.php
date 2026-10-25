<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Services\MinecraftStatusService;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Подвал, статус Minecraft-сервера и команда проекта.
 */
class SiteInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_server_status_without_configured_host(): void
    {
        config(['services.minecraft.host' => null]);

        $this->getJson('/api/site/server-status')
            ->assertOk()
            ->assertJson(['configured' => false, 'online' => false]);
    }

    public function test_server_status_parses_ping_and_strips_colors(): void
    {
        config(['services.minecraft.host' => 'play.example.com', 'services.minecraft.address' => 'mc-apex.ru']);

        $this->app->instance(MinecraftStatusService::class, new class extends MinecraftStatusService {
            public int $calls = 0;

            protected function ping(string $host, int $port): ?array
            {
                $this->calls++;

                return [
                    'players' => ['online' => 42, 'max' => 100],
                    'version' => ['name' => 'Paper 1.21'],
                    'description' => ['text' => '§aAPEX ', 'extra' => [['text' => '§lPvP']]],
                ];
            }
        });

        $this->getJson('/api/site/server-status')
            ->assertOk()
            ->assertJson([
                'configured' => true,
                'online' => true,
                'address' => 'mc-apex.ru',
                'players' => 42,
                'max' => 100,
                'version' => 'Paper 1.21',
                'motd' => 'APEX PvP',
            ]);

        // Второй запрос — из кэша, без пинга
        $this->getJson('/api/site/server-status')->assertOk();
        $this->assertSame(1, app(MinecraftStatusService::class)->calls);
    }

    public function test_unreachable_server_is_offline(): void
    {
        config(['services.minecraft.host' => 'play.example.com']);

        $this->app->instance(MinecraftStatusService::class, new class extends MinecraftStatusService {
            protected function ping(string $host, int $port): ?array
            {
                return null;
            }
        });

        $this->getJson('/api/site/server-status')
            ->assertOk()
            ->assertJson(['configured' => true, 'online' => false, 'players' => null]);
    }

    public function test_team_lists_staff_by_role_with_test_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'boss']);
        $busy = User::factory()->create(['role' => 'tester', 'username' => 'busy']);
        $quiet = User::factory()->create(['role' => 'tester', 'username' => 'quiet']);
        User::factory()->create(['role' => 'player']);

        $player = User::factory()->create();

        foreach (range(1, 3) as $i) {
            TierTest::withoutEvents(fn () => TierTest::create([
                'user_id' => $player->id,
                'tester_id' => $busy->id,
                'mode' => 'pvp',
                'status' => 'completed',
                'result_tier' => 'C',
                'result_score' => 45,
            ]));
        }

        $team = $this->getJson('/api/site/team')->assertOk()->json('team');

        $this->assertSame(['boss', 'busy', 'quiet'], array_column($team, 'username'));
        $this->assertSame(3, $team[1]['tests']);
        $this->assertSame('Тестер', $team[1]['role_label']);
    }
}
