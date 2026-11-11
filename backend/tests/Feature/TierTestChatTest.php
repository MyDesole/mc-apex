<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Notifications\TierTestMessageNotification;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Чат тестируемого с тестером: первым пишет тестер, чужим вход закрыт.
 */
class TierTestChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(array $overrides = []): array
    {
        $player = User::factory()->create(['role' => 'user']);
        $tester = User::factory()->create(['role' => 'tester']);
        $test = TierTest::create(array_merge([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'in_progress',
            'tester_id' => $tester->id,
            'claimed_by' => $tester->id,
            'claimed_at' => now(),
        ], $overrides));

        return [$player, $tester, $test];
    }

    public function test_player_cannot_write_before_tester(): void
    {
        [$player, , $test] = $this->makeTest();

        $this->actingAs($player)->getJson("/api/tier-tests/{$test->id}/chat")
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('role', 'player');

        $this->actingAs($player)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'привет'])
            ->assertStatus(422);
    }

    public function test_player_can_answer_after_tester_writes(): void
    {
        Notification::fake();
        [$player, $tester, $test] = $this->makeTest();

        $this->actingAs($tester)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'Привет! Когда удобно?'])
            ->assertCreated();

        Notification::assertSentTo($player, TierTestMessageNotification::class);

        $this->actingAs($player)->getJson("/api/tier-tests/{$test->id}/chat")
            ->assertJsonPath('can_send', true)
            ->assertJsonCount(1, 'messages');

        $this->actingAs($player)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'Сегодня вечером'])
            ->assertCreated();

        Notification::assertSentTo($tester, TierTestMessageNotification::class);
    }

    public function test_chat_is_closed_until_tester_claims_the_request(): void
    {
        [$player, , $test] = $this->makeTest(['status' => 'pending', 'tester_id' => null, 'claimed_by' => null]);

        $this->actingAs($player)->getJson("/api/tier-tests/{$test->id}/chat")
            ->assertOk()
            ->assertJsonPath('can_send', false);
    }

    public function test_outsiders_have_no_access(): void
    {
        [, , $test] = $this->makeTest();
        $stranger = User::factory()->create(['role' => 'user']);

        $this->actingAs($stranger)->getJson("/api/tier-tests/{$test->id}/chat")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'x'])->assertForbidden();
    }

    public function test_messages_are_marked_read_and_summary_counts_unread(): void
    {
        Notification::fake();
        [$player, $tester, $test] = $this->makeTest();

        $this->actingAs($tester)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'раз']);
        $this->actingAs($tester)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'два']);

        $this->actingAs($player)->getJson('/api/tier-chat')
            ->assertOk()
            ->assertJsonPath('chats.0.unread', 2)
            ->assertJsonPath('chats.0.role', 'player');

        $this->actingAs($player)->getJson("/api/tier-tests/{$test->id}/chat")->assertOk();

        $this->actingAs($player)->getJson('/api/tier-chat')->assertJsonPath('chats.0.unread', 0);
    }

    public function test_chat_is_read_only_after_completion(): void
    {
        [, $tester, $test] = $this->makeTest(['status' => 'completed']);

        $this->actingAs($tester)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => 'привет'])
            ->assertStatus(422);
    }

    public function test_empty_message_is_rejected(): void
    {
        [, $tester, $test] = $this->makeTest();

        $this->actingAs($tester)->postJson("/api/tier-tests/{$test->id}/chat", ['body' => '   '])
            ->assertStatus(422);
    }
}
