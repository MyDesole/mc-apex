<?php

namespace Tests\Feature;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Server\Services\GameServerService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Файловый менеджер и метрики панели сервера: проверки путей, роли, аудит. */
class ServerFilesTest extends TestCase
{
    use RefreshDatabase;

    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Вместо ssh — запись вызовов и готовые ответы
        $this->fake = new class extends GameServerService {
            public array $calls = [];

            public string $out = '';

            protected function exec(array $args, int $timeout = 20, ?string $stdin = null): array
            {
                $this->calls[] = ['remote' => end($args), 'stdin' => $stdin];

                return ['code' => 0, 'out' => $this->out, 'err' => ''];
            }
        };

        $this->instance(GameServerService::class, $this->fake);
    }

    private function as(string $role): self
    {
        return $this->actingAs(User::factory()->create(['role' => $role]));
    }

    public function test_roles_only_server_admin_and_admin(): void
    {
        $this->as('moderator')->getJson('/api/admin/server/fm/tree')->assertForbidden();
        $this->as('user')->getJson('/api/admin/server/metrics')->assertForbidden();
        $this->as('server_admin')->getJson('/api/admin/server/fm/tree')->assertOk();
        $this->as('admin')->getJson('/api/admin/server/fm/tree')->assertOk();
    }

    public function test_path_traversal_is_rejected_before_ssh(): void
    {
        foreach (['../etc/passwd', '/etc/passwd', 'plugins/../../x.yml', 'a;rm -rf.yml', "a\nb.yml"] as $bad) {
            $this->as('admin')->getJson('/api/admin/server/fm/read?path=' . urlencode($bad))->assertStatus(422);
        }

        $this->assertSame([], $this->fake->calls);
    }

    public function test_binary_files_cannot_be_read_or_saved(): void
    {
        $this->as('admin')->putJson('/api/admin/server/fm/write', ['path' => 'server.jar', 'content' => 'x'])->assertStatus(422);
        $this->as('admin')->getJson('/api/admin/server/fm/read?path=world/region/r.0.0.mca')->assertStatus(422);
        $this->assertSame([], $this->fake->calls);
    }

    public function test_save_goes_through_script_and_is_audited(): void
    {
        $this->fake->out = "saved\nbackup=/opt/minecraft/panel-backups/20261103-1/server.properties\n";

        $this->as('server_admin')
            ->putJson('/api/admin/server/fm/write', ['path' => 'server.properties', 'content' => 'motd=APEX'])
            ->assertOk()
            ->assertJsonPath('backup', '/opt/minecraft/panel-backups/20261103-1/server.properties');

        $this->assertStringContainsString('apex-files.sh put', $this->fake->calls[0]['remote']);
        $this->assertSame('motd=APEX', $this->fake->calls[0]['stdin']);
        $this->assertDatabaseHas('moderation_logs', ['action' => 'server.file_save']);
    }

    public function test_trash_requires_confirmation(): void
    {
        $this->as('admin')->postJson('/api/admin/server/fm/trash', ['path' => 'plugins/x.yml'])->assertStatus(422);
        $this->assertSame([], $this->fake->calls);

        $this->as('admin')->postJson('/api/admin/server/fm/trash', ['path' => 'plugins/x.yml', 'confirm' => true])->assertOk();
        $this->assertStringContainsString('apex-files.sh trash', $this->fake->calls[0]['remote']);
        $this->assertSame(1, ModerationLog::where('action', 'server.file_trash')->count());
    }

    public function test_tree_parses_entries(): void
    {
        $this->fake->out = "d\t4096\t2026-11-03 10:00\tplugins\nf\t120\t2026-11-03 10:01\tserver.properties\n";

        $this->as('admin')->getJson('/api/admin/server/fm/tree')
            ->assertOk()
            ->assertJsonPath('entries.0.type', 'dir')
            ->assertJsonPath('entries.1.name', 'server.properties');
    }

    public function test_metrics_parse(): void
    {
        $this->fake->out = "cpu=12\ncores=4\njava_rss=2048\ntps=19.98\nonline=2\nnames=Steve, Alex\ndisk_pct=7\n";

        $this->as('admin')->getJson('/api/admin/server/metrics')
            ->assertOk()
            ->assertJsonPath('tps', 19.98)
            ->assertJsonPath('names', ['Steve', 'Alex']);
    }

    public function test_console_command_and_power_are_audited(): void
    {
        $this->fake->out = 'sent';
        $this->as('admin')->postJson('/api/admin/server/command', ['command' => 'say hi'])->assertOk();
        $this->as('admin')->postJson('/api/admin/server/restart')->assertOk();

        $this->assertDatabaseHas('moderation_logs', ['action' => 'server.command']);
        $this->assertDatabaseHas('moderation_logs', ['action' => 'server.power']);
    }
}
