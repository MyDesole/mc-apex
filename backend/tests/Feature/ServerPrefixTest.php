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

        $prefix = app(ServerPrefixService::class)->compose($user->fresh());

        $this->assertStringStartsWith('&bMVP&c+ &#f97316A', $prefix);
        $this->assertStringNotContainsString('[', $prefix);
        $this->assertStringEndsWith(' &r', $prefix);
    }

    public function test_without_donate_only_tier(): void
    {
        $prefix = app(ServerPrefixService::class)->compose($this->player());

        $this->assertSame('&#f97316A &r', $prefix);
    }

    public function test_highest_rank_wins_and_expired_is_ignored(): void
    {
        $user = $this->player();
        $ranks = app(ServerRankService::class);
        $ranks->grant($user, 'vip', null);
        $ranks->grant($user, 'apex', 10);
        $ranks->grant($user, 'legend', 1);
        \DB::table('server_rank_grants')->where('rank', 'legend')->update(['expires_at' => now()->subHour()]);

        $this->assertStringStartsWith('&6APEX ', app(ServerPrefixService::class)->compose($user->fresh()));
    }

    public function test_sync_sends_only_changes(): void
    {
        $user = $this->player();
        MinecraftGrant::query()->delete();

        $service = app(ServerPrefixService::class);
        $this->assertGreaterThanOrEqual(1, $service->sync());

        $grant = MinecraftGrant::where('minecraft_username', 'Steve_1')->first();
        $this->assertSame(['lp user Steve_1 meta setprefix 1000 "&#f97316A &r"'], $grant->commands);

        // Ничего не менялось: второй проход тихий
        $count = MinecraftGrant::count();
        $service->sync();
        $this->assertSame($count, MinecraftGrant::count());

        // Тир сменился: уходит новая команда
        $user->forceFill(['tier' => 'S'])->save();
        $service->sync();
        $this->assertSame($count + 1, MinecraftGrant::count());
    }

    public function test_failed_prefix_is_resent_when_player_comes_online(): void
    {
        $this->player();
        $service = app(ServerPrefixService::class);
        $service->sync();

        // Игрок ещё не заходил: LuckPerms его не знал, команда провалилась
        MinecraftGrant::query()->update(['status' => MinecraftGrant::FAILED]);
        $service->sync();
        $this->assertSame(0, MinecraftGrant::where('status', MinecraftGrant::PENDING)->count());

        // Зашёл на сервер: отправляем снова
        \Cache::put(\App\Domains\Minecraft\Controllers\Api\MinecraftIntegrationController::ONLINE_KEY, ['steve_1'], 60);
        $service->sync();
        $this->assertSame(1, MinecraftGrant::where('status', MinecraftGrant::PENDING)->count());

        // и не зацикливаемся
        $service->sync();
        $this->assertSame(1, MinecraftGrant::where('status', MinecraftGrant::PENDING)->count());
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
        $this->assertContains('lp user Alex_9 meta setprefix 1000 "&#f97316A &r"', $all);
    }
}
