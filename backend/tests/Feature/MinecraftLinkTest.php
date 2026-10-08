<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftLinkCode;
use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Привязка майнкрафт-игрока к аккаунту и вход по паролю сайта.
 *
 * Схема: игрок заходит на сервер, получает код, вводит его на сайте под
 * своей сессией. Пароль при привязке не участвует — иначе плагин видел бы
 * его до того, как игрок подтвердил, что это его аккаунт.
 */
class MinecraftLinkTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-server-key';

    private const UUID = '069a79f4-44e9-4726-a5be-fca90e38aaf5';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.minecraft.server_key' => self::KEY]);

        RateLimiter::clear('minecraft-auth:' . self::UUID);
        RateLimiter::clear('minecraft-link-code:' . self::UUID);
    }

    private function asServer(array $payload, string $url, string $key = self::KEY)
    {
        return $this->withHeaders(['X-Minecraft-Server-Key' => $key])
            ->postJson($url, $payload);
    }

    private function service(): MinecraftLinkService
    {
        return app(MinecraftLinkService::class);
    }

    /* ---------------- Доступ ---------------- */

    public function test_endpoints_require_server_key(): void
    {
        $this->postJson('/api/minecraft/link/start', [
            'uuid' => self::UUID,
            'username' => 'Notch',
        ])->assertForbidden();
    }

    public function test_wrong_server_key_is_rejected(): void
    {
        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
            'wrong-key',
        )->assertForbidden();
    }

    public function test_endpoints_are_closed_without_configured_key(): void
    {
        config(['services.minecraft.server_key' => null]);

        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
            'anything',
        )->assertForbidden();
    }

    /* ---------------- Код привязки ---------------- */

    public function test_start_link_returns_code(): void
    {
        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )
            ->assertOk()
            ->assertJsonPath('already_linked', false)
            ->assertJsonStructure(['code', 'formatted_code', 'expires_in']);

        $this->assertDatabaseHas('minecraft_link_codes', [
            'uuid' => self::UUID,
            'username' => 'Notch',
        ]);
    }

    public function test_code_is_eight_chars_without_confusing_symbols(): void
    {
        $code = MinecraftLinkCode::generate();

        $this->assertSame(8, strlen($code));
        $this->assertDoesNotMatchRegularExpression('/[O0IL1]/', $code);
    }

    public function test_repeated_start_replaces_previous_code(): void
    {
        $first = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        $second = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, MinecraftLinkCode::query()->where('uuid', self::UUID)->count());
    }

    public function test_status_reports_linked_and_pending(): void
    {
        $this->asServer(['uuid' => self::UUID], '/api/minecraft/link/status')
            ->assertOk()
            ->assertJsonPath('linked', false)
            ->assertJsonPath('code_pending', false);

        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->assertOk();

        $this->asServer(['uuid' => self::UUID], '/api/minecraft/link/status')
            ->assertOk()
            ->assertJsonPath('code_pending', true);
    }

    /* ---------------- Привязка на сайте ---------------- */

    public function test_player_links_account_with_code(): void
    {
        $user = User::factory()->create(['username' => 'site_player']);

        $code = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('username', 'Notch');

        $user->refresh();

        $this->assertSame(self::UUID, $user->minecraft_uuid);
        $this->assertSame('Notch', $user->minecraft_username);

        // Код одноразовый
        $this->assertSame(0, MinecraftLinkCode::query()->count());
    }

    public function test_code_can_be_entered_with_dashes_and_lowercase(): void
    {
        $user = User::factory()->create();

        $code = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        $formatted = strtolower(MinecraftLinkCode::format($code));

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['code' => $formatted])->assertOk();

        $this->assertSame(self::UUID, $user->fresh()->minecraft_uuid);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->assertOk();

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['code' => 'AAAAAAAA'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->minecraft_uuid);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $code = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        MinecraftLinkCode::query()->where('uuid', self::UUID)->update([
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/players/me/minecraft/link', ['code' => $code])->assertStatus(422);

        $this->assertNull($user->fresh()->minecraft_uuid);
    }

    public function test_uuid_already_taken_by_another_account_is_rejected(): void
    {
        $owner = User::factory()->create(['minecraft_uuid' => self::UUID]);
        $other = User::factory()->create();

        $code = $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->json('code');

        Sanctum::actingAs($other);

        $this->postJson('/api/players/me/minecraft/link', ['code' => $code])->assertStatus(422);

        $this->assertNull($other->fresh()->minecraft_uuid);
        $this->assertSame($owner->id, $owner->fresh()->id);
    }

    public function test_player_can_unlink(): void
    {
        $user = User::factory()->create(['minecraft_uuid' => self::UUID, 'minecraft_username' => 'Notch']);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/players/me/minecraft/link')->assertOk()->assertJsonPath('linked', false);

        $this->assertNull($user->fresh()->minecraft_uuid);
    }

    public function test_guest_cannot_link(): void
    {
        $this->postJson('/api/players/me/minecraft/link', ['code' => 'AAAAAAAA'])->assertUnauthorized();
    }

    /* ---------------- Вход ---------------- */

    public function test_login_with_correct_password(): void
    {
        User::factory()->create([
            'username' => 'site_player',
            'password' => 'secret-password',
            'minecraft_uuid' => self::UUID,
            'minecraft_username' => 'Notch',
        ]);

        $this->asServer(
            ['uuid' => self::UUID, 'password' => 'secret-password'],
            '/api/minecraft/auth/login',
        )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('player.username', 'site_player');
    }

    public function test_login_with_wrong_password(): void
    {
        User::factory()->create([
            'password' => 'secret-password',
            'minecraft_uuid' => self::UUID,
        ]);

        $this->asServer(
            ['uuid' => self::UUID, 'password' => 'wrong-password'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('attempts_left', MinecraftLinkService::MAX_ATTEMPTS - 1);
    }

    public function test_unknown_uuid_gives_same_answer_as_wrong_password(): void
    {
        $this->asServer(
            ['uuid' => self::UUID, 'password' => 'any-password'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(401)
            ->assertJsonPath('ok', false);
    }

    public function test_login_is_throttled_after_max_attempts(): void
    {
        User::factory()->create([
            'password' => 'secret-password',
            'minecraft_uuid' => self::UUID,
        ]);

        for ($i = 0; $i < MinecraftLinkService::MAX_ATTEMPTS; $i++) {
            $this->asServer(
                ['uuid' => self::UUID, 'password' => 'wrong'],
                '/api/minecraft/auth/login',
            )->assertStatus(401);
        }

        // Дальше — блокировка, даже с верным паролем
        $this->asServer(
            ['uuid' => self::UUID, 'password' => 'secret-password'],
            '/api/minecraft/auth/login',
        )
            ->assertStatus(429)
            ->assertJsonPath('reason', 'throttled');
    }

    public function test_successful_login_resets_attempts(): void
    {
        User::factory()->create([
            'password' => 'secret-password',
            'minecraft_uuid' => self::UUID,
        ]);

        $this->asServer(['uuid' => self::UUID, 'password' => 'wrong'], '/api/minecraft/auth/login')
            ->assertStatus(401);

        $this->asServer(['uuid' => self::UUID, 'password' => 'secret-password'], '/api/minecraft/auth/login')
            ->assertOk();

        $this->assertSame(0, RateLimiter::attempts('minecraft-auth:' . self::UUID));
    }

    public function test_login_requires_password(): void
    {
        $this->asServer(['uuid' => self::UUID], '/api/minecraft/auth/login')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /* ---------------- UUID ---------------- */

    public function test_uuid_without_dashes_is_normalized(): void
    {
        User::factory()->create(['minecraft_uuid' => self::UUID, 'password' => 'secret-password']);

        $this->asServer(
            ['uuid' => '069a79f444e94726a5befca90e38aaf5', 'password' => 'secret-password'],
            '/api/minecraft/auth/login',
        )->assertOk()->assertJsonPath('ok', true);
    }

    public function test_uuid_is_hidden_from_api_responses(): void
    {
        $user = User::factory()->create([
            'minecraft_uuid' => self::UUID,
            'minecraft_username' => 'Notch',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me')->assertOk();

        $this->assertArrayNotHasKey('minecraft_uuid', $response->json('user'));
    }

    /* ---------------- Профиль для игры ---------------- */

    public function test_profile_endpoint_returns_player_data(): void
    {
        User::factory()->create([
            'username' => 'site_player',
            'tier' => 'B',
            'minecraft_uuid' => self::UUID,
        ]);

        $this->asServer(['uuid' => self::UUID], '/api/minecraft/profile')
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('player.username', 'site_player')
            ->assertJsonPath('player.tier', 'B');
    }

    public function test_profile_endpoint_for_unlinked_player(): void
    {
        $this->asServer(['uuid' => self::UUID], '/api/minecraft/profile')
            ->assertStatus(404)
            ->assertJsonPath('linked', false);
    }

    /* ---------------- Ограничение выдачи кодов ---------------- */

    public function test_link_codes_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->asServer(
                ['uuid' => self::UUID, 'username' => 'Notch'],
                '/api/minecraft/link/start',
            )->assertOk();
        }

        $this->asServer(
            ['uuid' => self::UUID, 'username' => 'Notch'],
            '/api/minecraft/link/start',
        )->assertStatus(422);
    }
}
