<?php

namespace Tests\Feature;

use App\Models\TierTest;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Очередь тир-тестов: приоритет важнее времени создания.
 *
 * Здесь порядок по дате ПРОТИВОПОЛОЖЕН порядку по приоритету, поэтому тест
 * различает две реализации: с учётом приоритета и без него.
 */
class TierTestQueuePriorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    private function tester(): User
    {
        return User::factory()->tester()->create();
    }

    private function makeRequest(User $player, \DateTimeInterface $createdAt, array $overrides = []): TierTest
    {
        $test = TierTest::create(array_merge([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1234',
            'preferred_time' => 'сегодня 20:00',
            'status' => 'pending',
        ], $overrides));

        $test->created_at = $createdAt;
        $test->save();

        return $test;
    }

    /**
     * Тяжёлый приоритет создан РАНЬШЕ, лёгкий — ПОЗЖЕ.
     * По дате первым был бы лёгкий; по приоритету — тяжёлый.
     */
    public function test_priority_weight_beats_creation_time(): void
    {
        $tester = $this->tester();
        $player = User::factory()->create(['apex_coins' => 0]);

        $heavy = $this->makeRequest($player, now()->subDays(3), [
            'is_priority' => true,
            'priority_weight' => 500,
        ]);

        $light = $this->makeRequest($player, now(), [
            'is_priority' => true,
            'priority_weight' => 100,
        ]);

        $queue = collect(
            $this->actingAs($tester)
                ->getJson('/api/tester/tier-tests?status=pending')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame(
            [$heavy->id, $light->id],
            $queue,
            'Заявка с большим приоритетом должна быть выше, даже если создана раньше'
        );
    }

    /**
     * Приоритетная заявка выше обычной, даже если обычная новее.
     */
    public function test_priority_beats_newer_regular_request(): void
    {
        $tester = $this->tester();
        $player = User::factory()->create(['apex_coins' => 0]);

        $priority = $this->makeRequest($player, now()->subDays(4), [
            'is_priority' => true,
            'priority_weight' => 100,
        ]);

        $regular = $this->makeRequest($player, now(), [
            'is_priority' => false,
            'priority_weight' => 0,
        ]);

        $queue = collect(
            $this->actingAs($tester)
                ->getJson('/api/tester/tier-tests?status=pending')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame([$priority->id, $regular->id], $queue);
    }

    /**
     * Внутри одного приоритета сохраняется FIFO: кто раньше, тот выше.
     */
    public function test_equal_priority_keeps_fifo_order(): void
    {
        $tester = $this->tester();
        $player = User::factory()->create(['apex_coins' => 0]);

        $older = $this->makeRequest($player, now()->subDays(5), [
            'is_priority' => true,
            'priority_weight' => 100,
        ]);

        $newer = $this->makeRequest($player, now()->subDay(), [
            'is_priority' => true,
            'priority_weight' => 100,
        ]);

        $queue = collect(
            $this->actingAs($tester)
                ->getJson('/api/tester/tier-tests?status=pending')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $queue);
    }
}
