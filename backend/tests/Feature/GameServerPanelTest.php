<?php

namespace Tests\Feature;

use App\Domains\Server\Services\GameServerService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

/**
 * Панель игрового сервера: доступ по ролям и работа эндпоинтов.
 *
 * SSH в тестах не нужен: сервис подменяется заглушкой, которая
 * запоминает вызовы.
 */
class GameServerPanelTest extends TestCase
{
    use RefreshDatabase;

    /** Заглушка: те же методы, но без ssh. */
    private function fakeServer(): object
    {
        $fake = new class extends GameServerService {
            public array $calls = [];

            public function status(): array
            {
                return [
                    'host' => '168.113.181.145',
                    'running' => true,
                    'pid' => 9722,
                    'uptime' => '10:36:05',
                    'jar' => 'WindSpigot-2.1.4.jar',
                    'players' => 3,
                    'mem_total' => 7838,
                    'mem_used' => 6247,
                    'disk_used' => '3.3G',
                    'disk_total' => '79G',
                    'load' => '0.06',
                    'log_size' => '76K',
                    'log_updated' => '2026-11-03 09:16:41',
                ];
            }

            public function players(): array
            {
                return ['online' => 3, 'max' => 20];
            }

            public function plugins(): array
            {
                return [
                    ['name' => 'ProtocolLib.jar', 'size' => '10012 KB'],
                    ['name' => 'multiworld.jar', 'size' => '384 KB'],
                ];
            }

            public function console(int $lines = 150): string
            {
                return "[09:00:00] [Server thread/INFO]: Done (12.3s)!\n[09:16:41] [Server thread/INFO]: test line";
            }

            public function start(): string
            {
                $this->calls[] = 'start';

                return 'started';
            }

            public function stop(): string
            {
                $this->calls[] = 'stop';

                return 'stopped';
            }

            public function restart(): string
            {
                $this->calls[] = 'restart';

                return 'restarted';
            }

            public function command(string $command): string
            {
                $this->calls[] = 'cmd:' . $command;

                return 'sent';
            }

            public function startCommand(): string
            {
                return '#!/usr/bin/env bash\nexec java -jar WindSpigot-2.1.4.jar nogui';
            }

            public function saveStartCommand(string $content): void
            {
                $this->calls[] = 'save-start';
            }
        };

        $this->instance(GameServerService::class, $fake);

        return $fake;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /* ==================== Доступ ==================== */

    public function test_player_cannot_open_server_panel(): void
    {
        $this->actingAs($this->user('user'))->getJson('/api/admin/server/status')->assertForbidden();
        $this->actingAs($this->user('user'))->postJson('/api/admin/server/restart')->assertForbidden();
    }

    public function test_tester_cannot_open_server_panel(): void
    {
        $this->actingAs($this->user('tester'))->getJson('/api/admin/server/status')->assertForbidden();
    }

    public function test_moderator_cannot_open_server_panel(): void
    {
        // Панель сервера — только «Администратор сайта» и админ
        $this->actingAs($this->user('moderator'))->getJson('/api/admin/server/status')->assertForbidden();
    }

    public function test_site_admin_can_read_status_and_console(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('site_admin'))
            ->getJson('/api/admin/server/status')
            ->assertOk()
            ->assertJsonPath('running', true)
            ->assertJsonPath('players', 3)
            ->assertJsonPath('jar', 'WindSpigot-2.1.4.jar');

        $this->actingAs($this->user('site_admin'))
            ->getJson('/api/admin/server/console?lines=50')
            ->assertOk()
            ->assertJsonPath('log', fn ($log) => str_contains($log, 'Done'));
    }

    public function test_site_admin_gets_exact_players(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('site_admin'))
            ->getJson('/api/admin/server/players')
            ->assertOk()
            ->assertJsonPath('online', 3)
            ->assertJsonPath('max', 20);
    }

    public function test_site_admin_sees_plugins(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('site_admin'))
            ->getJson('/api/admin/server/plugins')
            ->assertOk()
            ->assertJsonCount(2, 'plugins')
            ->assertJsonPath('plugins.0.name', 'ProtocolLib.jar');
    }

    public function test_upload_target_config_is_rejected(): void
    {
        $this->fakeServer();

        // Каталога config на сервере нет — загрузка туда не разрешена
        $this->actingAs($this->user('site_admin'))
            ->postJson('/api/admin/server/upload', ['target' => 'config'])
            ->assertStatus(422);
    }

    public function test_site_admin_can_restart_and_send_command(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('site_admin');

        $this->actingAs($admin)->postJson('/api/admin/server/restart')->assertOk();
        $this->assertContains('restart', $fake->calls);

        $this->actingAs($admin)
            ->postJson('/api/admin/server/command', ['command' => 'say привет'])
            ->assertOk();

        $this->assertContains('cmd:say привет', $fake->calls);
    }

    public function test_site_admin_can_read_and_save_start_command(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('site_admin');

        $this->actingAs($admin)
            ->getJson('/api/admin/server/start-command')
            ->assertOk()
            ->assertJsonPath('content', fn ($content) => str_contains($content, 'java'));

        $this->actingAs($admin)
            ->putJson('/api/admin/server/start-command', ['content' => '#!/usr/bin/env bash\nexec java -Xmx5G -jar WindSpigot-2.1.4.jar nogui'])
            ->assertOk();

        $this->assertContains('save-start', $fake->calls);
    }

    public function test_admin_can_open_server_panel(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('admin'))->getJson('/api/admin/server/status')->assertOk();
    }

    /* ==================== Валидация ==================== */

    public function test_empty_command_is_rejected(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('site_admin'))
            ->postJson('/api/admin/server/command', ['command' => ''])
            ->assertStatus(422);
    }

    public function test_start_command_requires_content(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('site_admin'))
            ->putJson('/api/admin/server/start-command', ['content' => ''])
            ->assertStatus(422);
    }

    public function test_upload_rejects_dangerous_extension(): void
    {
        // Проверяем именно сервис: до ssh дело не доходит
        $file = UploadedFile::fake()->create('evil.php', 10);

        $this->expectException(RuntimeException::class);

        app(GameServerService::class)->upload($file, '');
    }

    /* ==================== Роль ==================== */

    public function test_site_admin_role_has_server_rights(): void
    {
        $user = $this->user('site_admin');

        $this->assertTrue($user->isSiteAdmin());
        $this->assertTrue($user->canManageServer());
        $this->assertSame('Администратор сайта', $user->roleLabel());
        $this->assertTrue($this->user('admin')->canManageServer());
        $this->assertFalse($this->user('moderator')->canManageServer());
    }
}
