<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Одна активная заявка на тир-тест у игрока.
 *
 * Раньше можно было отправить сколько угодно заявок подряд: тестеры
 * получали дубли, а в очереди висели повторы.
 */
class TierTestSingleActiveTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'сегодня вечером',
        ];
    }

    public function test_first_request_is_created(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->postJson('/api/tier-tests', $this->payload())
            ->assertCreated()
            ->assertJsonPath('tier_test.status', 'pending');

        $this->assertSame(1, TierTest::query()->count());
    }

    public function test_second_pending_request_is_rejected(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->postJson('/api/tier-tests', $this->payload())->assertCreated();

        $this->postJson('/api/tier-tests', $this->payload())
            ->assertStatus(422);

        $this->assertSame(
            1,
            TierTest::query()->count(),
            'Вторая заявка не должна создаваться'
        );
    }

    public function test_request_in_progress_also_blocks(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'tester']);

        TierTest::create([
            'user_id' => $player->id,
            'tester_id' => $tester->id,
            'claimed_by' => $tester->id,
            'claimed_at' => now(),
            'mode' => 'pvp',
            'status' => 'in_progress',
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'сейчас',
        ]);

        Sanctum::actingAs($player);

        $this->postJson('/api/tier-tests', $this->payload())
            ->assertStatus(422);
    }

    public function test_completed_request_does_not_block(): void
    {
        $player = User::factory()->create();

        TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'completed',
            'completed_at' => now(),
            'result_tier' => 'B',
            'result_score' => 60,
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'вчера',
        ]);

        Sanctum::actingAs($player);

        // История тестов может быть любой длины — новая заявка разрешена
        $this->postJson('/api/tier-tests', $this->payload())->assertCreated();
    }

    public function test_cancelled_request_does_not_block(): void
    {
        $player = User::factory()->create();

        TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'cancelled',
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'вчера',
        ]);

        Sanctum::actingAs($player);

        $this->postJson('/api/tier-tests', $this->payload())->assertCreated();
    }

    public function test_another_player_is_not_blocked(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($first);

        $this->postJson('/api/tier-tests', $this->payload())->assertCreated();

        Sanctum::actingAs($second);

        $this->postJson('/api/tier-tests', $this->payload())->assertCreated();

        $this->assertSame(2, TierTest::query()->count());
    }

    public function test_history_shows_the_tester(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'tester', 'username' => 'ProTester']);

        TierTest::create([
            'user_id' => $player->id,
            'tester_id' => $tester->id,
            'mode' => 'pvp',
            'status' => 'completed',
            'completed_at' => now(),
            'result_tier' => 'A',
            'result_score' => 75,
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'вчера',
        ]);

        Sanctum::actingAs($player);

        $response = $this->getJson("/api/players/{$player->id}/tier-history")->assertOk();

        $response->assertJsonPath('history.0.tester.username', 'ProTester');
        $response->assertJsonPath('history.0.result_tier', 'A');
    }

    public function test_manual_test_by_admin_closes_the_active_request(): void
    {
        $player = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $active = TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'pending',
            'contact_type' => 'discord',
            'contact_value' => 'tester#0001',
            'preferred_time' => 'завтра',
        ]);

        app(\App\Domains\Tiers\Services\TierTestService::class)->conductManually(
            $player,
            $admin,
            'pvp',
            [
                'block_placing' => 15, 'rotka' => 15, 'movement' => 15,
                'aim' => 15, 'game_sense' => 15,
            ],
            'Проведено вручную',
        );

        $this->assertSame(
            'cancelled',
            $active->fresh()->status,
            'Активная заявка снимается с очереди, а не остаётся висеть'
        );

        /*
         * Записей две: отменённая заявка и проведённый тест. Старую
         * удалять нельзя — иначе из истории пропадёт, что игрок
         * записывался и ждал тестера.
         */
        $this->assertSame(2, TierTest::query()->where('user_id', $player->id)->count());
        $this->assertSame(
            1,
            TierTest::query()->where('user_id', $player->id)->where('status', 'completed')->count()
        );
    }
}
