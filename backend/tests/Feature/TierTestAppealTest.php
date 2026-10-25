<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestAppeal;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Апелляции на результат тир-теста.
 */
class TierTestAppealTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Тестер не засчитал мувмент, хотя я всё сделал правильно.';

    private const ASPECTS = ['block_placing' => 16, 'rotka' => 15, 'movement' => 18, 'aim' => 15, 'game_sense' => 14];

    private User $player;

    private User $tester;

    private TierTest $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->create();
        $this->tester = User::factory()->create(['role' => 'tester']);
        $this->test = $this->completedTest(now()->subDay());
    }

    private function completedTest($completedAt): TierTest
    {
        return TierTest::withoutEvents(fn () => TierTest::create([
            'user_id' => $this->player->id,
            'tester_id' => $this->tester->id,
            'mode' => 'pvp',
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => 45,
            'completed_at' => $completedAt,
        ]));
    }

    private function appeal(): TierTestAppeal
    {
        return TierTestAppeal::create([
            'tier_test_id' => $this->test->id,
            'user_id' => $this->player->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_player_can_appeal_own_recent_test_once(): void
    {
        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$this->test->id}/appeal", ['reason' => self::REASON])
            ->assertCreated()
            ->assertJsonPath('appeal.status', 'pending');

        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$this->test->id}/appeal", ['reason' => self::REASON])
            ->assertUnprocessable();
    }

    public function test_cannot_appeal_foreign_old_or_outdated_test(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->postJson("/api/tier-tests/{$this->test->id}/appeal", ['reason' => self::REASON])
            ->assertForbidden();

        $old = $this->completedTest(now()->subDays(30));
        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$old->id}/appeal", ['reason' => self::REASON])
            ->assertUnprocessable();

        // Есть тест новее — старый уже не оспорить
        $this->completedTest(now());
        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$this->test->id}/appeal", ['reason' => self::REASON])
            ->assertUnprocessable();
    }

    public function test_history_shows_appeal_flags_only_to_owner(): void
    {
        $this->actingAs($this->player)
            ->getJson("/api/players/{$this->player->id}/tier-history")
            ->assertOk()
            ->assertJsonPath('history.0.can_appeal', true);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/players/{$this->player->id}/tier-history")
            ->assertOk()
            ->assertJsonMissingPath('history.0.can_appeal');
    }

    public function test_moderator_rejects_with_reason_and_player_is_notified(): void
    {
        $appeal = $this->appeal();
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)
            ->postJson("/api/admin/tier-test-appeals/{$appeal->id}/resolve", ['decision' => 'rejected'])
            ->assertUnprocessable();

        $this->actingAs($moderator)
            ->postJson("/api/admin/tier-test-appeals/{$appeal->id}/resolve", [
                'decision' => 'rejected',
                'resolution' => 'На записи видно, что аим ниже заявленного.',
            ])
            ->assertOk()
            ->assertJsonPath('appeal.status', 'rejected');

        $this->assertSame(1, $this->player->notifications()->count());
        $this->assertSame('tier_test_appeal_resolved', $this->player->notifications()->first()->data['type']);
    }

    public function test_approval_creates_revised_test_and_recalculates_tier(): void
    {
        $appeal = $this->appeal();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson("/api/admin/tier-test-appeals/{$appeal->id}/resolve", [
                'decision' => 'approved',
                'aspects' => self::ASPECTS,
            ])
            ->assertOk()
            ->assertJsonPath('appeal.status', 'approved')
            ->assertJsonPath('appeal.revised_test.result_tier', 'A');

        $this->assertSame(2, TierTest::where('user_id', $this->player->id)->where('status', 'completed')->count());
        $this->assertSame('A', $this->player->fresh()->tier);
    }

    public function test_original_tester_cannot_review_and_players_cannot_access_queue(): void
    {
        $appeal = $this->appeal();
        $this->tester->update(['role' => 'moderator']);

        $this->actingAs($this->tester)
            ->postJson("/api/admin/tier-test-appeals/{$appeal->id}/resolve", ['decision' => 'rejected', 'resolution' => 'Нет.'])
            ->assertForbidden();

        $this->actingAs($this->player)
            ->getJson('/api/admin/tier-test-appeals')
            ->assertForbidden();
    }
}
