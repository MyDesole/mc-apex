<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Minecraft\Services\ServerPrefixService;
use App\Domains\Minecraft\Services\ServerRankService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerPrefixTest extends TestCase
{
    use RefreshDatabase;

    private function player(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['minecraft_username' => 'Steve_1', 'tier' => 'A', 'tier_score' => 50]);
    }

    public function test_prefix_is_donate_then_tier_without_brackets(): void
    {
        $user = $this->player();
        app(ServerRankService::class)->grant($user, 'mvp_plus', 30);

        $strings = app(ServerPrefixService::class)->compose($user->fresh());

        $this->assertSame('&3&lᴍ&b&lᴠᴘ&c&l+ &c&lA &r', $strings['prefix']);
        $this->assertStringNotContainsString('[', $strings['prefix']);
        $this->assertStringNotContainsString('&#', $strings['prefix'], 'на 1.8 hex-цветов нет');
    }

    public function test_tag_over_head_never_exceeds_sixteen_chars(): void
    {
        $service = app(ServerPrefixService::class);

        foreach (array_keys(ServerRankService::RANKS) as $rank) {
            foreach (['S+', 'A', 'E'] as $tier) {
                $user = User::factory()->create(['minecraft_username' => 'P' . random_int(1000, 9999), 'tier' => $tier, 'tier_score' => 10]);
                app(ServerRankService::class)->grant($user, $rank, null);

                $tag = $service->compose($user->fresh())['tag'];

                $this->assertLessThanOrEqual(16, mb_strlen($tag), "{$rank} {$tier}: {$tag}");
                $this->assertStringEndsWith('&r', $tag);
            }
        }
    }

    public function test_without_donate_only_tier(): void
    {
        $strings = app(ServerPrefixService::class)->compose($this->player());

        $this->assertSame('&c&lA &r', $strings['prefix']);
        $this->assertSame('&c&lA &r', $strings['tag']);
    }

    public function test_highest_rank_wins_and_expired_is_ignored(): void
    {
        $user = $this->player();
        $ranks = app(ServerRankService::class);
        $ranks->grant($user, 'vip', null);
        $ranks->grant($user, 'apex', 10);
        $ranks->grant($user, 'legend', 1);
        \DB::table('server_rank_grants')->where('rank', 'legend')->update(['expires_at' => now()->subHour()]);

        $this->assertStringStartsWith('&6&lᴀᴘ&e&lᴇx ', app(ServerPrefixService::class)->compose($user->fresh())['prefix']);
    }

    public function test_sync_sends_prefix_and_tag_only_on_change(): void
    {
        $user = $this->player();
        MinecraftGrant::query()->delete();

        $service = app(ServerPrefixService::class);
        $this->assertGreaterThanOrEqual(1, $service->sync());

        $grant = MinecraftGrant::where('minecraft_username', 'Steve_1')->first();
        $this->assertSame([
            'lp user Steve_1 meta setprefix 1000 "&c&lA &r"',
            'lp user Steve_1 meta setsuffix 1000 "&c&lA &r"',
        ], $grant->commands);

        // Ничего не менялось: второй проход тихий
        $count = MinecraftGrant::count();
        $service->sync();
        $this->assertSame($count, MinecraftGrant::count());

        // Тир сменился: уходит новая команда
        $user->forceFill(['tier' => 'S'])->save();
        $service->sync();
        $this->assertSame($count + 1, MinecraftGrant::count());
    }

    public function test_prefix_is_resent_when_player_joins(): void
    {
        $this->player();
        $service = app(ServerPrefixService::class);
        $service->sync();
        MinecraftGrant::query()->delete();

        // Зашёл на сервер: LuckPerms мог не знать его раньше, шлём префикс снова
        $service->onJoin(['steve_1']);

        $grant = MinecraftGrant::first();
        $this->assertSame('Steve_1', $grant->minecraft_username);
        $this->assertSame('lp user Steve_1 meta setprefix 1000 "&c&lA &r"', $grant->commands[0]);

        // Чужой ник ничего не создаёт
        MinecraftGrant::query()->delete();
        $service->onJoin(['nobody']);
        $this->assertSame(0, MinecraftGrant::count());
    }

    public function test_site_admin_gets_admin_rank_on_sync(): void
    {
        $admin = $this->player(['role' => 'admin']);

        app(ServerPrefixService::class)->sync();

        $this->assertDatabaseHas('server_rank_grants', ['user_id' => $admin->id, 'rank' => 'admin', 'source' => 'role']);
        $all = MinecraftGrant::all()->flatMap(fn ($g) => $g->commands)->all();
        $this->assertContains('lp user Steve_1 parent add admin', $all);
        $this->assertContains('lp user Steve_1 meta setprefix 1000 "&c&lᴀᴅᴍɪɴ &c&lA &r"', $all);
    }

    public function test_server_tiers_endpoint_returns_ready_prefix_for_chat(): void
    {
        config(['services.minecraft.server_key' => 'test-key']);
        $this->player();
        app(ServerPrefixService::class)->sync();

        $row = $this->withHeaders(['X-Minecraft-Server-Key' => 'test-key'])
            ->getJson('/api/minecraft/tiers?nicks=Steve_1')
            ->assertOk()
            ->json('tiers.0');

        $this->assertSame('Steve_1', $row['nick']);
        $this->assertSame('&c&lA &r', $row['prefix']);
    }

    public function test_nick_change_clears_old_nick(): void
    {
        $user = $this->player();
        $service = app(ServerPrefixService::class);
        $service->sync();

        $user->forceFill(['minecraft_username' => 'Alex_9'])->save();
        MinecraftGrant::query()->delete();
        $service->sync();

        $all = MinecraftGrant::all()->flatMap(fn ($g) => $g->commands)->all();
        $this->assertContains('lp user Steve_1 meta removeprefix 1000', $all);
        $this->assertContains('lp user Steve_1 meta removesuffix 1000', $all);
        $this->assertContains('lp user Alex_9 meta setprefix 1000 "&c&lA &r"', $all);
    }
}
