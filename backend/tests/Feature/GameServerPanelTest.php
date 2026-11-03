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

            public function files(): array
            {
                return [
                    ['path' => 'server.properties', 'size' => '4 KB', 'group' => 'root'],
                    ['path' => 'bukkit.yml', 'size' => '4 KB', 'group' => 'root'],
                    ['path' => 'plugins/Alley/settings.yml', 'size' => '4 KB', 'group' => 'plugins'],
                ];
            }

            public function readFile(string $path): string
            {
                return "view-distance=5\nmotd=APEX";
            }

            public function saveFile(string $path, string $content): void
            {
                $this->calls[] = 'save-file:' . $path;
            }

            public function archives(): array
            {
                return [['name' => 'server-full.zip', 'size' => '52M', 'created' => '2026-11-03 11:00']];
            }

            public function backups(): array
            {
                return [['name' => 'server-20261103-111957.tar.gz', 'size' => '72M', 'created' => '2026-11-03 11:19']];
            }

            public function uploadArchive(\Illuminate\Http\UploadedFile $file): array
            {
                $this->calls[] = 'upload-archive';

                return ['name' => 'pack.zip', 'size' => 1024];
            }

            public function applyArchive(string $name): array
            {
                $this->calls[] = 'apply:' . $name;

                return ['applied' => true, 'backup' => 'server-20261103-120000.tar.gz', 'output' => 'applied'];
            }

            public function deleteArchive(string $name): void
            {
                $this->calls[] = 'delete-archive:' . $name;
            }

            public function restoreBackup(string $name): string
            {
                $this->calls[] = 'restore:' . $name;

                return 'restored';
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
        // Панель сервера — только «Администратор сервера» и админ
        $this->actingAs($this->user('moderator'))->getJson('/api/admin/server/status')->assertForbidden();
    }

    public function test_server_admin_can_read_status_and_console(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/status')
            ->assertOk()
            ->assertJsonPath('running', true)
            ->assertJsonPath('players', 3)
            ->assertJsonPath('jar', 'WindSpigot-2.1.4.jar');

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/console?lines=50')
            ->assertOk()
            ->assertJsonPath('log', fn ($log) => str_contains($log, 'Done'));
    }

    public function test_server_admin_gets_exact_players(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/players')
            ->assertOk()
            ->assertJsonPath('online', 3)
            ->assertJsonPath('max', 20);
    }

    public function test_server_admin_sees_plugins(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/plugins')
            ->assertOk()
            ->assertJsonCount(2, 'plugins')
            ->assertJsonPath('plugins.0.name', 'ProtocolLib.jar');
    }

    public function test_upload_target_config_is_rejected(): void
    {
        $this->fakeServer();

        // Каталога config на сервере нет — загрузка туда не разрешена
        $this->actingAs($this->user('server_admin'))
            ->postJson('/api/admin/server/upload', ['target' => 'config'])
            ->assertStatus(422);
    }

    /* ==================== Конфиги ==================== */

    public function test_server_admin_sees_config_files(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/files')
            ->assertOk()
            ->assertJsonCount(3, 'files')
            ->assertJsonPath('files.0.path', 'server.properties')
            ->assertJsonPath('files.2.group', 'plugins');
    }

    public function test_server_admin_reads_and_saves_config(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('server_admin');

        $this->actingAs($admin)
            ->getJson('/api/admin/server/file?path=server.properties')
            ->assertOk()
            ->assertJsonPath('path', 'server.properties')
            ->assertJsonPath('content', fn ($c) => str_contains($c, 'view-distance'));

        $this->actingAs($admin)
            ->putJson('/api/admin/server/file', [
                'path' => 'plugins/Alley/settings.yml',
                'content' => 'plugin:\n  auto-update: false',
            ])
            ->assertOk();

        $this->assertContains('save-file:plugins/Alley/settings.yml', $fake->calls);
    }

    public function test_config_path_traversal_is_rejected(): void
    {
        // Проверяем сам сервис: до ssh дело не доходит
        $service = app(GameServerService::class);

        foreach (['../../etc/passwd', '/etc/passwd', 'plugins/../../secret.yml'] as $bad) {
            try {
                $service->readFile($bad);
                $this->fail('путь не заблокирован: ' . $bad);
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_config_extension_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(GameServerService::class)->readFile('plugins/Secret.jar');
    }

    /* ==================== Архивы ==================== */

    public function test_server_admin_sees_archives_and_backups(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->getJson('/api/admin/server/archives')
            ->assertOk()
            ->assertJsonPath('archives.0.name', 'server-full.zip')
            ->assertJsonPath('backups.0.name', 'server-20261103-111957.tar.gz');
    }

    public function test_server_admin_applies_archive(): void
    {
        $fake = $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->postJson('/api/admin/server/archives/server-full.zip/apply')
            ->assertOk()
            ->assertJsonPath('result.applied', true)
            ->assertJsonPath('result.backup', 'server-20261103-120000.tar.gz');

        $this->assertContains('apply:server-full.zip', $fake->calls);
    }

    public function test_server_admin_uploads_archive(): void
    {
        $fake = $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
            ->post('/api/admin/server/archives', [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('pack.zip', 2048),
            ])
            ->assertOk()
            ->assertJsonPath('archive.name', 'pack.zip');

        $this->assertContains('upload-archive', $fake->calls);
    }

    public function test_archive_name_must_be_zip(): void
    {
        // Имя проверяет сервис, до scp не доходим
        $this->expectException(RuntimeException::class);

        app(GameServerService::class)->uploadArchive(
            \Illuminate\Http\UploadedFile::fake()->create('evil.txt', 10),
        );
    }

    public function test_archive_apply_rejects_foreign_role(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('moderator'))
            ->postJson('/api/admin/server/archives/server-full.zip/apply')
            ->assertForbidden();
    }

    public function test_server_admin_deletes_archive_and_restores_backup(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('server_admin');

        $this->actingAs($admin)
            ->deleteJson('/api/admin/server/archives/old.zip')
            ->assertOk();

        $this->assertContains('delete-archive:old.zip', $fake->calls);

        $this->actingAs($admin)
            ->postJson('/api/admin/server/backups/server-20261103-111957.tar.gz/restore')
            ->assertOk();

        $this->assertContains('restore:server-20261103-111957.tar.gz', $fake->calls);
    }

    public function test_server_admin_can_restart_and_send_command(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('server_admin');

        $this->actingAs($admin)->postJson('/api/admin/server/restart')->assertOk();
        $this->assertContains('restart', $fake->calls);

        $this->actingAs($admin)
            ->postJson('/api/admin/server/command', ['command' => 'say привет'])
            ->assertOk();

        $this->assertContains('cmd:say привет', $fake->calls);
    }

    public function test_server_admin_can_read_and_save_start_command(): void
    {
        $fake = $this->fakeServer();
        $admin = $this->user('server_admin');

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

        $this->actingAs($this->user('server_admin'))
            ->postJson('/api/admin/server/command', ['command' => ''])
            ->assertStatus(422);
    }

    public function test_start_command_requires_content(): void
    {
        $this->fakeServer();

        $this->actingAs($this->user('server_admin'))
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

    public function test_server_admin_role_can_be_assigned_via_admin_api(): void
    {
        $admin = $this->user('admin');
        $target = $this->user('user');

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/role", ['role' => 'server_admin'])
            ->assertOk();

        $this->assertSame('server_admin', $target->fresh()->role);
    }

    public function test_server_admin_role_is_assignable_but_not_ranked(): void
    {
        $user = $this->user('server_admin');

        $this->assertTrue($user->canManageServer());
        $this->assertTrue($user->isUnranked());
        $this->assertTrue($user->isStaff());
        $this->assertFalse($user->isModerator());
        $this->assertFalse($user->isAdmin());
    }

    /* ==================== Роль ==================== */

    public function test_server_admin_role_has_server_rights(): void
    {
        $user = $this->user('server_admin');

        $this->assertTrue($user->isServerAdmin());
        $this->assertTrue($user->canManageServer());
        $this->assertSame('Администратор сервера', $user->roleLabel());
        $this->assertTrue($this->user('admin')->canManageServer());
        $this->assertFalse($this->user('moderator')->canManageServer());
    }
}
