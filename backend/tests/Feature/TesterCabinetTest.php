<?php

namespace Tests\Feature;

use App\Domains\Testers\Models\TesterEarning;
use App\Domains\Testers\Models\TesterPayout;
use App\Domains\Testers\Models\TesterSlot;
use App\Domains\Testers\Services\TesterEarningsService;
use App\Domains\Testers\Support\TesterRates;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кабинет тестера: календарь, запись игрока, деньги и права доступа.
 */
class TesterCabinetTest extends TestCase
{
    use RefreshDatabase;

    private User $tester;
    private User $player;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester = User::factory()->create(['role' => 'tester']);
        $this->player = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** Заявка на тест: по умолчанию ждёт в очереди. */
    private function makeTest(array $overrides = []): TierTest
    {
        return TierTest::create(array_merge([
            'user_id' => $this->player->id,
            'mode' => 'pvp',
            'status' => 'pending',
        ], $overrides));
    }

    /** Открыть слот тестером напрямую через API. */
    private function openSlot(string $from = '+2 hours', string $to = '+3 hours'): TesterSlot
    {
        $response = $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->parse($from)->toDateTimeString(),
            'ends_at' => now()->parse($to)->toDateTimeString(),
        ])->assertCreated();

        return TesterSlot::findOrFail($response->json('slot.id'));
    }

    /* ==================== Календарь ==================== */

    public function test_tester_opens_a_slot(): void
    {
        $slot = $this->openSlot();

        $this->assertSame('open', $slot->status);
        $this->assertSame($this->tester->id, $slot->user_id);
    }

    public function test_slot_in_the_past_is_rejected(): void
    {
        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->subHours(3)->toDateTimeString(),
            'ends_at' => now()->subHours(2)->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_slots_cannot_overlap(): void
    {
        $this->openSlot('+2 hours', '+4 hours');

        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHours(3)->toDateTimeString(),
            'ends_at' => now()->addHours(5)->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_slot_duration_is_limited(): void
    {
        // Пять часов — дольше разрешённых четырёх
        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(6)->toDateTimeString(),
        ])->assertStatus(422);

        // Пятнадцать минут — короче получаса
        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHour()->addMinutes(15)->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_tester_sees_only_own_slots(): void
    {
        $this->openSlot();

        $other = User::factory()->create(['role' => 'tester']);

        $this->actingAs($other)->getJson('/api/tester/slots')
            ->assertOk()
            ->assertJsonCount(0, 'slots');
    }

    /* ==================== Запись игрока ==================== */

    public function test_player_books_a_slot(): void
    {
        $slot = $this->openSlot();
        $test = $this->makeTest();

        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$test->id}/book", ['slot_id' => $slot->id])
            ->assertOk();

        $this->assertSame('booked', $slot->fresh()->status);
        $this->assertSame($test->id, $slot->fresh()->tier_test_id);
        $this->assertNotNull($test->fresh()->scheduled_at);
    }

    public function test_slot_cannot_be_booked_twice(): void
    {
        $slot = $this->openSlot();

        $first = $this->makeTest();
        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$first->id}/book", ['slot_id' => $slot->id])
            ->assertOk();

        // Вторая заявка на то же время
        $second = $this->makeTest();

        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$second->id}/book", ['slot_id' => $slot->id])
            ->assertStatus(422);
    }

    public function test_booking_requires_pending_test(): void
    {
        $slot = $this->openSlot();
        $test = $this->makeTest(['status' => 'in_progress', 'claimed_by' => $this->tester->id]);

        $this->actingAs($this->player)
            ->postJson("/api/tier-tests/{$test->id}/book", ['slot_id' => $slot->id])
            ->assertStatus(422);
    }

    public function test_open_slots_are_visible_to_players(): void
    {
        $this->openSlot();

        $this->actingAs($this->player)
            ->getJson('/api/tester-slots')
            ->assertOk()
            ->assertJsonCount(1, 'testers')
            ->assertJsonPath('testers.0.tester.id', $this->tester->id)
            ->assertJsonCount(1, 'testers.0.slots');
    }

    /* ==================== Отмена освобождает слот ==================== */

    public function test_cancelling_test_releases_slot(): void
    {
        $slot = $this->openSlot();
        $test = $this->makeTest(['status' => 'in_progress', 'claimed_by' => $this->tester->id]);
        $test->update(['scheduled_at' => $slot->starts_at]);
        $slot->update(['status' => 'booked', 'tier_test_id' => $test->id]);

        $this->actingAs($this->tester)
            ->postJson("/api/tester/tier-tests/{$test->id}/cancel", ['reason' => 'Не смог'])
            ->assertOk();

        $this->assertSame('open', $slot->fresh()->status);
        $this->assertNull($slot->fresh()->tier_test_id);
    }

    /* ==================== Заработок ==================== */

    public function test_completed_test_accrues_earnings_by_tier(): void
    {
        $test = $this->makeTest([
            'status' => 'completed',
            'tester_id' => $this->tester->id,
            'claimed_by' => $this->tester->id,
            'result_tier' => 'S+',
            'result_score' => 98,
            'completed_at' => now(),
        ]);

        $earning = app(TesterEarningsService::class)->accrue($test);

        $expected = TesterRates::reward('S+');

        $this->assertSame($expected['rub'], $earning->rub);
        $this->assertSame($expected['coins'], $earning->coins);

        // Сильный тир платит больше слабого
        $this->assertGreaterThan(TesterRates::reward('E')['rub'], $earning->rub);

        // Повторное начисление по тому же тесту не создаёт второе
        app(TesterEarningsService::class)->accrue($test->fresh());

        $this->assertSame(1, TesterEarning::where('tier_test_id', $test->id)->count());
    }

    public function test_summary_counts_earned_and_paid(): void
    {
        foreach (['S+', 'C'] as $index => $tier) {
            $this->makeTest([
                'status' => 'completed',
                'tester_id' => $this->tester->id,
                'claimed_by' => $this->tester->id,
                'result_tier' => $tier,
                'completed_at' => now(),
            ]);
        }

        foreach (TierTest::where('tester_id', $this->tester->id)->get() as $test) {
            app(TesterEarningsService::class)->accrue($test);
        }

        $summary = app(TesterEarningsService::class)->summary($this->tester);

        $this->assertSame(2, $summary['tests']);
        $this->assertGreaterThan(0, $summary['month_rub']);
        $this->assertSame(0, $summary['paid_rub']);
        $this->assertSame($summary['earned_rub'], $summary['available_rub']);
    }

    /* ==================== Выплаты ==================== */

    public function test_payout_request_and_payment(): void
    {
        $test = $this->makeTest([
            'status' => 'completed',
            'tester_id' => $this->tester->id,
            'claimed_by' => $this->tester->id,
            'result_tier' => 'A',
            'completed_at' => now(),
        ]);

        app(TesterEarningsService::class)->accrue($test);

        $this->actingAs($this->tester)
            ->postJson('/api/tester/payouts')
            ->assertCreated();

        $payout = TesterPayout::where('user_id', $this->tester->id)->firstOrFail();

        $this->assertSame(TesterPayout::PENDING, $payout->status);
        $this->assertGreaterThan(0, $payout->rub);

        // Пока запрос не рассмотрен, второй создать нельзя
        $this->actingAs($this->tester)->postJson('/api/tester/payouts')->assertStatus(422);

        // Стартовые монеты не мешают: считаем прирост баланса
        $before = (int) $this->tester->fresh()->apex_coins;

        // Админ отмечает выплату
        $this->actingAs($this->admin)
            ->postJson("/api/admin/testers/payouts/{$payout->id}/pay", ['comment' => 'Перевёл на карту'])
            ->assertOk();

        $this->assertSame(TesterPayout::PAID, $payout->fresh()->status);

        // Монеты ушли на кошелёк ровно на сумму выплаты
        $this->assertSame(
            $payout->coins,
            (int) $this->tester->fresh()->apex_coins - $before,
        );

        $summary = app(TesterEarningsService::class)->summary($this->tester->fresh());
        $this->assertSame($payout->rub, $summary['paid_rub']);
        $this->assertSame(0, $summary['available_rub']);
    }

    public function test_nothing_to_payout_is_rejected(): void
    {
        $this->actingAs($this->tester)->postJson('/api/tester/payouts')->assertStatus(422);
    }

    /* ==================== Права и блокировка ==================== */

    public function test_player_cannot_open_tester_cabinet(): void
    {
        $this->actingAs($this->player)->getJson('/api/tester/dashboard')->assertForbidden();
        $this->actingAs($this->player)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_tester_cannot_use_admin_endpoints(): void
    {
        $this->actingAs($this->tester)->getJson('/api/admin/testers')->assertForbidden();
        $this->actingAs($this->tester)
            ->putJson('/api/admin/testers/settings', [
                'pay_rub' => 100,
                'pay_coins' => 1000,
                'tier_multipliers' => [],
            ])
            ->assertForbidden();
    }

    public function test_blocked_tester_cannot_open_slots(): void
    {
        $this->actingAs($this->admin)->postJson("/api/admin/testers/{$this->tester->id}/block", [
            'until' => now()->addWeek()->toDateTimeString(),
            'reason' => 'АФК',
        ])->assertOk();

        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertStatus(422);

        // Разблокировали — снова можно
        $this->actingAs($this->admin)
            ->postJson("/api/admin/testers/{$this->tester->id}/unblock")
            ->assertOk();

        $this->actingAs($this->tester)->postJson('/api/tester/slots', [
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertCreated();
    }

    /* ==================== Экономика ==================== */

    public function test_admin_changes_rates(): void
    {
        $this->actingAs($this->admin)->putJson('/api/admin/testers/settings', [
            'pay_rub' => 100,
            'pay_coins' => 1000,
            'tier_multipliers' => ['S+' => 3],
        ])->assertOk();

        $this->assertSame(100, TesterRates::settings()['pay_rub']);
        $this->assertSame(300, TesterRates::reward('S+')['rub']);
        $this->assertSame(100, TesterRates::reward('C')['rub']);
        $this->assertSame(1000, TesterRates::reward('C')['coins']);
    }

    public function test_dashboard_returns_summary(): void
    {
        $this->openSlot();

        $this->actingAs($this->tester)
            ->getJson('/api/tester/dashboard')
            ->assertOk()
            ->assertJsonPath('profile.blocked', false)
            ->assertJsonPath('rates.pay_rub', TesterRates::DEFAULT_RUB)
            ->assertJsonCount(1, 'slots')
            ->assertJsonStructure([
                'stats' => ['completed', 'avg_score', 'cancelled', 'tiers'],
                'earnings' => ['month_rub', 'month_coins', 'paid_rub', 'available_rub', 'available_coins'],
                'payouts', 'history', 'queue',
            ]);
    }

    public function test_admin_sees_all_testers_and_tests(): void
    {
        $this->makeTest();

        $this->actingAs($this->admin)->getJson('/api/admin/testers')
            ->assertOk()
            ->assertJsonFragment(['username' => $this->tester->username]);

        $this->actingAs($this->admin)->getJson('/api/admin/testers/tests')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'user', 'tester', 'status', 'scheduled_at']]]);
    }
}
