<?php

namespace Tests\Feature;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Действия с аккаунтом игрока из досье и раздел рефералов.
 */
class PlayerAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->moderator = User::factory()->create(['role' => 'moderator']);
        $this->player = User::factory()->create([
            'username' => 'OldNick',
            'avatar' => 'users/1/a.jpg',
            'bio' => 'реклама казино',
            'email_verified_at' => null,
            'minecraft_username' => 'Steve',
        ]);
    }

    public function test_admin_renames_player_with_validation(): void
    {
        $taken = User::factory()->create(['username' => 'Taken']);

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/rename", ['username' => 'bad nick!'])
            ->assertUnprocessable();
        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/rename", ['username' => $taken->username])
            ->assertUnprocessable();

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/rename", ['username' => 'NewNick'])
            ->assertOk();

        $this->assertSame('NewNick', $this->player->fresh()->username);
        $this->assertSame(['from' => 'OldNick', 'to' => 'NewNick'], ModerationLog::where('action', 'user.rename')->first()->details);

        // Модератору смена ника недоступна
        $this->actingAs($this->moderator)->postJson("/api/admin/users/{$this->player->id}/rename", ['username' => 'Other'])
            ->assertForbidden();
    }

    public function test_moderator_cleans_profile_but_not_staff(): void
    {
        $this->actingAs($this->moderator)->postJson("/api/admin/users/{$this->player->id}/remove-avatar")->assertOk();
        $this->actingAs($this->moderator)->postJson("/api/admin/users/{$this->player->id}/clear-profile")->assertOk();
        $this->actingAs($this->moderator)->postJson("/api/admin/users/{$this->player->id}/note", ['text' => 'следить за рекламой'])->assertOk();

        $fresh = $this->player->fresh();
        $this->assertNull($fresh->avatar);
        $this->assertNull($fresh->bio);
        $this->assertTrue(ModerationLog::where('action', 'user.note')->exists());

        $tester = User::factory()->create(['role' => 'tester']);
        $this->actingAs($this->moderator)->postJson("/api/admin/users/{$tester->id}/remove-avatar")->assertForbidden();
    }

    public function test_password_email_session_and_minecraft_actions(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/send-password-reset")->assertOk();

        $password = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$this->player->id}/temporary-password")
            ->assertOk()
            ->json('password');
        $this->assertTrue(Hash::check($password, $this->player->fresh()->password));

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/verify-email")->assertOk();
        $this->assertNotNull($this->player->fresh()->email_verified_at);
        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/verify-email")->assertUnprocessable();

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/logout-everywhere")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/unlink-minecraft")->assertOk();
        $this->assertNull($this->player->fresh()->minecraft_username);

        // Письмо, пароль, почта, сессии, Minecraft — повторное подтверждение почты отклонено
        $this->assertSame(5, ModerationLog::where('target_user_id', $this->player->id)->count());
    }

    public function test_logout_everywhere_deletes_file_sessions_of_this_player_only(): void
    {
        $dir = storage_path('framework/testing-sessions');
        @mkdir($dir, 0777, true);
        config(['session.driver' => 'file', 'session.files' => $dir]);

        $mine = $dir.'/player-session';
        $other = $dir.'/other-session';
        file_put_contents($mine, serialize(['login_web_abc' => $this->player->id, '_token' => 'x']));
        file_put_contents($other, serialize(['login_web_abc' => $this->admin->id]));

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->player->id}/logout-everywhere")->assertOk();

        $this->assertFileDoesNotExist($mine);
        $this->assertFileExists($other);

        @unlink($other);
        @rmdir($dir);
    }

    public function test_referrals_in_dossier_and_admin_section(): void
    {
        $friend = User::factory()->create(['referred_by' => $this->player->id, 'tier_score' => 40]);
        User::factory()->create(['referred_by' => $this->player->id]);

        $this->actingAs($this->moderator)->getJson("/api/admin/users/{$friend->id}/dossier")
            ->assertOk()
            ->assertJsonPath('referrals.invited_by.id', $this->player->id)
            ->assertJsonStructure(['forum' => ['topics_count', 'replies_count', 'topics', 'replies']]);

        $this->actingAs($this->moderator)->getJson("/api/admin/users/{$this->player->id}/dossier")
            ->assertOk()
            ->assertJsonPath('referrals.invited_count', 2);

        $this->actingAs($this->admin)->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('totals.invited', 2)
            ->assertJsonPath('top.0.id', $this->player->id)
            ->assertJsonPath('top.0.invited', 2)
            ->assertJsonPath('top.0.invited_active', 1);

        $this->actingAs($this->moderator)->getJson('/api/admin/referrals')->assertForbidden();
    }
}
