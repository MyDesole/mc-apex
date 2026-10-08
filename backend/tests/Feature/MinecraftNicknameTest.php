<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Привязка к аккаунту по нику.
 *
 * Игрок заявляет ник в профиле под своей сессией, ник уникален на сайте,
 * а плагин пускает только того, чей ник в игре совпадает с заявленным.
 * Поэтому зайти под чужим ником нельзя: плагин потребует пароль владельца
 * заявки, а не того, кто зашёл.
 */
class MinecraftNicknameTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-server-key';

    private const NICK = 'MyDesole';

    private const UUID = '069a79f4-44e9-4726-a5be-fca90e38aaf5';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.minecraft.server_key' => self::KEY]);

        RateLimiter::clear('minecraft-auth:' . mb_strtolower(self::NICK));
    }

    private function asServer(array $payload, string $url)
    {
        return $this->withHeaders(['X-Minecraft-Server-Key' => self::KEY])
            ->postJson($url, $payload);
    }

    private function claimed(string $nickname = self::NICK, string $password = 'site-password'): User
    {
        return User::factory()->create([
            'username' => 'site_' . mb_strtolower($nickname),
            'password' => $password,
            'minecraft_username' => $nickname,
            'minecraft_linked_at' => now(),
        ]);
    }

    /* ---------------- Доступ ---------------- */

    public function test_endpoints_require_server_key(): void
    {
        $this->postJson('/api/minecraft/nickname/resolve', ['nickname' => self::NICK])
            ->assertForbidden();
    }

    public function test_endpoints_closed_without_configured_key(): void
    {
        config(['services.minecraft.server_key' => null]);

        $this->withHeaders(['X-Minecraft-Server-Key' => 'anything'])
            ->postJson('/api/minecraft/nickname/resolve', ['nickname' => self::NICK])
            ->assertForbidden();
    }

    /* ---------------- Заявка ника на сайте ---------------- */

    public function test_player_claims_nickname(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['nickname' => self::NICK])
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('nickname', self::NICK);

        $this->assertSame(self::NICK, $user->fresh()->minecraft_username);
    }

    public function test_nickname_must_look_like_a_minecraft_nick(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        foreach (['ab', 'слишкомдлинныйникнейм', 'with space', 'with-dash', ''] as $bad) {
            $this->postJson('/api/players/me/minecraft/link', ['nickname' => $bad])
                ->assertStatus(422);
        }

        $this->assertNull($user->fresh()->minecraft_username);
    }

    public function test_nickname_cannot_be_claimed_twice(): void
    {
        $this->claimed();

        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $this->postJson('/api/players/me/minecraft/link', ['nickname' => self::NICK])
            ->assertStatus(422);

        $this->assertNull($other->fresh()->minecraft_username);
    }

    public function test_claiming_is_case_insensitive(): void
    {
        $this->claimed(self::NICK);

        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $this->postJson('/api/players/me/minecraft/link', ['nickname' => 'mydesole'])
            ->assertStatus(422);
    }

    public function test_player_can_change_own_nickname(): void
    {
        $user = $this->claimed(self::NICK);

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['nickname' => 'NewNick'])
            ->assertOk()
            ->assertJsonPath('nickname', 'NewNick');

        $this->assertSame('NewNick', $user->fresh()->minecraft_username);
    }

    public function test_player_can_release_nickname(): void
    {
        $user = $this->claimed();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/players/me/minecraft/link')
            ->assertOk()
            ->assertJsonPath('claimed', false);

        $this->assertNull($user->fresh()->minecraft_username);
    }

    public function test_guest_cannot_claim(): void
    {
        $this->postJson('/api/players/me/minecraft/link', ['nickname' => self::NICK])
            ->assertUnauthorized();
    }

    /* ---------------- Плагин: заявлен ли ник ---------------- */

    public function test_resolve_reports_free_nickname(): void
    {
        $this->asServer(['nickname' => self::NICK], '/api/minecraft/nickname/resolve')
            ->assertOk()
            ->assertJsonPath('claimed', false);
    }

    public function test_resolve_reports_owner(): void
    {
        $user = $this->claimed();

        $this->asServer(['nickname' => self::NICK], '/api/minecraft/nickname/resolve')
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('account_username', $user->username);
    }

    public function test_resolve_ignores_case(): void
    {
        $this->claimed(self::NICK);

        $this->asServer(['nickname' => 'mydesole'], '/api/minecraft/nickname/resolve')
            ->assertOk()
            ->assertJsonPath('claimed', true);
    }

    /* ---------------- Вход ---------------- */

    public function test_login_by_nickname_with_correct_password(): void
    {
        $user = $this->claimed(self::NICK, 'site-password');

        $this->asServer(
            ['nickname' => self::NICK, 'password' => 'site-password', 'uuid' => self::UUID],
            '/api/minecraft/auth/login',
        )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('player.username', $user->username);

        // UUID запоминается при первом входе
        $this->assertSame(self::UUID, $user->fresh()->minecraft_uuid);
    }

    public function test_login_with_wrong_password(): void
    {
        $this->claimed(self::NICK, 'site-password');

        $this->asServer(
            ['nickname' => self::NICK, 'password' => 'wrong'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('attempts_left', MinecraftLinkService::MAX_ATTEMPTS - 1);
    }

    public function test_login_for_free_nickname_gives_same_answer(): void
    {
        $this->asServer(
            ['nickname' => 'Nobody', 'password' => 'any'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(401)
            ->assertJsonPath('ok', false);
    }

    public function test_login_is_throttled(): void
    {
        $this->claimed(self::NICK, 'site-password');

        for ($i = 0; $i < MinecraftLinkService::MAX_ATTEMPTS; $i++) {
            $this->asServer(
                ['nickname' => self::NICK, 'password' => 'wrong'],
                '/api/minecraft/auth/login',
            )->assertStatus(401);
        }

        $this->asServer(
            ['nickname' => self::NICK, 'password' => 'site-password'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(429)
            ->assertJsonPath('reason', 'throttled');
    }

    public function test_uuid_is_hidden_from_api(): void
    {
        $user = $this->claimed();

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('user.minecraft_uuid');
    }

    /* ---------------- Инструменты админа ---------------- */

    public function test_admin_sees_who_holds_nickname(): void
    {
        $user = $this->claimed();

        $this->asServer(['nickname' => self::NICK], '/api/minecraft/admin/lookup')
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('account_username', $user->username);
    }

    public function test_admin_releases_nickname(): void
    {
        $user = $this->claimed();

        $this->asServer(['nickname' => self::NICK], '/api/minecraft/admin/unlink')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull($user->fresh()->minecraft_username);

        // Ник снова свободен
        $this->asServer(['nickname' => self::NICK], '/api/minecraft/admin/lookup')
            ->assertOk()
            ->assertJsonPath('claimed', false);
    }

    public function test_admin_unlink_of_free_nickname_is_not_an_error(): void
    {
        $this->asServer(['nickname' => 'Nobody'], '/api/minecraft/admin/unlink')
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    /* ---------------- Профиль для игры ---------------- */

    public function test_profile_by_nickname(): void
    {
        $user = $this->claimed();

        $this->asServer(['nickname' => self::NICK], '/api/minecraft/profile')
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('player.username', $user->username)
            ->assertJsonPath('player.nickname', self::NICK);
    }

    public function test_profile_for_free_nickname(): void
    {
        $this->asServer(['nickname' => 'Nobody'], '/api/minecraft/profile')
            ->assertStatus(404)
            ->assertJsonPath('claimed', false);
    }
}
