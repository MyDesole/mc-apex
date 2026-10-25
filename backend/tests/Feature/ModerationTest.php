<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Chat\Models\Message;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Moderation\Models\Report;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Жалобы, решения модераторов, журнал, дашборд и досье.
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $reporter, User $target, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($reporter)->postJson('/api/reports', array_merge([
            'type' => 'user',
            'id' => $target->id,
            'reason' => 'cheating',
            'comment' => 'Летает над картой',
        ], $data));
    }

    public function test_player_reports_another_once_but_not_himself(): void
    {
        $reporter = User::factory()->create();
        $cheater = User::factory()->create();

        $this->report($reporter, $cheater)->assertCreated();
        $this->report($reporter, $cheater)->assertUnprocessable();
        $this->report($reporter, $reporter)->assertUnprocessable();

        $this->assertSame(1, Report::count());
        $this->assertSame($cheater->username, Report::first()->snapshot);
    }

    public function test_message_report_requires_access_to_conversation(): void
    {
        [$author, $reader, $stranger] = User::factory()->count(3)->create();
        $conversation = Conversation::create(['type' => 'direct', 'last_message_at' => now()]);
        foreach ([$author, $reader] as $user) {
            ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        }
        $message = Message::create(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'body' => 'обидные слова']);

        $this->report($stranger, $author, ['type' => 'message', 'id' => $message->id, 'reason' => 'insult'])
            ->assertForbidden();

        $this->report($reader, $author, ['type' => 'message', 'id' => $message->id, 'reason' => 'insult'])
            ->assertCreated();

        $this->assertSame($author->id, Report::first()->target_user_id);
        $this->assertSame('обидные слова', Report::first()->snapshot);
    }

    public function test_moderator_bans_by_report_and_related_reports_close(): void
    {
        $cheater = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->report($first, $cheater);
        $this->report($second, $cheater);
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)
            ->postJson('/api/admin/reports/'.Report::first()->id.'/resolve', ['action' => 'ban', 'ban_days' => 7, 'note' => 'подтверждено записью'])
            ->assertOk()
            ->assertJsonPath('report.status', 'resolved');

        $cheater->refresh();
        $this->assertTrue($cheater->is_banned);
        $this->assertTrue($cheater->banned_until->isFuture());
        $this->assertSame(0, Report::where('status', 'open')->count());
        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
        $this->assertTrue(ModerationLog::where('action', 'user.ban')->where('target_user_id', $cheater->id)->exists());
    }

    public function test_warning_notifies_and_moderator_cannot_ban_staff(): void
    {
        $tester = User::factory()->create(['role' => 'tester']);
        $player = User::factory()->create();
        $reporter = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->report($reporter, $tester);
        $this->actingAs($moderator)
            ->postJson('/api/admin/reports/'.Report::first()->id.'/resolve', ['action' => 'ban'])
            ->assertForbidden();

        $this->report($reporter, $player, ['reason' => 'spam']);
        $this->actingAs($moderator)
            ->postJson('/api/admin/reports/'.Report::where('target_user_id', $player->id)->value('id').'/resolve', ['action' => 'warn'])
            ->assertOk();

        $this->assertSame('moderation_warning', $player->notifications()->first()->data['type']);
        $this->assertFalse($player->fresh()->is_banned);
    }

    public function test_players_cannot_open_moderation_endpoints(): void
    {
        $player = User::factory()->create();

        foreach (['/api/admin/reports', '/api/admin/dashboard', '/api/admin/counters', '/api/admin/moderation-log', "/api/admin/users/{$player->id}/dossier"] as $url) {
            $this->actingAs($player)->getJson($url)->assertForbidden();
        }
    }

    public function test_dashboard_counters_and_dossier(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['email' => 'secret@example.com']);
        $this->report(User::factory()->create(), $player);

        $this->actingAs($moderator)->getJson('/api/admin/counters')
            ->assertOk()->assertJsonPath('reports', 1);

        $this->actingAs($moderator)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('attention.reports', 1)
            ->assertJsonPath('economy', null)
            ->assertJsonCount(14, 'tests_by_day');

        $this->actingAs($admin)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['economy' => ['in_circulation', 'issued_week', 'spent_week']]);

        // Почту в досье видит только администратор
        $this->actingAs($moderator)->getJson("/api/admin/users/{$player->id}/dossier")
            ->assertOk()
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('reports_against_total', 1);

        $this->actingAs($admin)->getJson("/api/admin/users/{$player->id}/dossier")
            ->assertOk()
            ->assertJsonPath('user.email', 'secret@example.com');

        $this->actingAs($moderator)->getJson('/api/admin/players/search?q='.substr($player->username, 0, 4))
            ->assertOk()
            ->assertJsonPath('players.0.id', $player->id);
    }

    public function test_admin_actions_are_written_to_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/ban", ['reason' => 'читы'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/unban")->assertOk();

        $this->actingAs($admin)->getJson('/api/admin/moderation-log')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'user.unban')
            ->assertJsonPath('data.1.label', 'Бан');
    }
}
