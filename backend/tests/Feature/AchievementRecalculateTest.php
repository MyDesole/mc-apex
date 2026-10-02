<?php

namespace Tests\Feature;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Users\Models\User;
use App\Domains\Achievements\Services\AchievementService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Пересчёт ачивок и доплата за них.
 *
 * Команда нужна в двух случаях:
 *   - ачивка была выдана, когда награда в ApexCoin не начислялась
 *     (источник выключен или coin_reward не задан);
 *   - состав ачивок менялся, и часть из них у игроков не выставлена.
 */
class AchievementRecalculateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    /**
     * Сколько монет положено за все ачивки игрока.
     *
     * Учитываем весь набор: команда пересчитывает условия целиком,
     * поэтому кроме проверяемой ачивки могут появиться тировые.
     */
    private function expectedCoins(User $user): int
    {
        return $user->achievements()->get()
            ->sum(fn ($a) => \App\Domains\Wallet\Services\RewardService::coinsForAchievement($a));
    }

    private function runCommand(array $params = []): array
    {
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;

        $exit = \Illuminate\Support\Facades\Artisan::call(
            'apex:achievements',
            $params + ['--force' => true],
            $buffer
        );

        return [$exit, $buffer->fetch()];
    }

    /* ---------------------- Доплата за старые ачивки ---------------------- */

    public function test_achievement_earned_without_reward_gets_paid(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);
        $achievement = Achievement::byCode('tier_a');

        // Ачивка выставлена вручную, без награды
        $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(0, $user->fresh()->apex_coins);

        [$exit, $output] = $this->runCommand();

        $this->assertSame(0, $exit);

        $expected = $this->expectedCoins($user->fresh());
        $this->assertGreaterThan(0, $expected, 'Ачивки должны быть оплачены');

        $this->assertSame($expected, $user->fresh()->apex_coins);
        $this->assertGreaterThan(0, CoinTransaction::where('source', CoinTransaction::SOURCE_ACHIEVEMENT)->count());

        // Ачивка, за которую не платили, теперь оплачена
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'idempotency_key' => \App\Domains\Achievements\Services\AchievementService::rewardKey($user, $achievement),
        ]);

        $this->assertStringContainsString('Начислено', $output);
    }

    public function test_repeat_run_does_not_pay_twice(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);
        $user->achievements()->attach(Achievement::byCode('tier_a')->id, ['earned_at' => now()]);

        $this->runCommand();
        $afterFirst = $user->fresh()->apex_coins;

        $this->runCommand();
        $afterSecond = $user->fresh()->apex_coins;

        $this->assertSame($afterFirst, $afterSecond, 'Повторный запуск не должен платить дважды');
    }

    public function test_already_paid_achievements_are_reported_as_paid(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);

        // Приводим игрока в согласованное состояние: все положенные
        // ачивки выданы и оплачены
        AchievementService::check($user);

        $before = $user->fresh()->apex_coins;
        $paidBefore = CoinTransaction::where('source', CoinTransaction::SOURCE_ACHIEVEMENT)->count();

        $this->assertGreaterThan(0, $before, 'Ачивки должны быть уже оплачены');

        [$exit, $output] = $this->runCommand();

        $this->assertSame(0, $exit);
        $this->assertSame($before, $user->fresh()->apex_coins, 'Повторно платить нельзя');
        $this->assertSame(
            $paidBefore,
            CoinTransaction::where('source', CoinTransaction::SOURCE_ACHIEVEMENT)->count(),
            'Новых транзакций быть не должно'
        );
        $this->assertStringContainsString('Все ачивки уже оплачено', $output);
    }

    /* ---------------------- Пересчёт состава ачивок ---------------------- */

    public function test_missing_achievements_are_granted(): void
    {
        $leader = User::factory()->create(['tier' => 'C']);

        $clan = Clan::create([
            'name' => 'Клан ачивок',
            'tag' => 'ACH',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        // Ни одной ачивки у игрока нет, хотя условия выполнены
        $this->assertSame(0, $leader->achievements()->count());

        $this->runCommand();

        $codes = $leader->fresh()->achievements()->pluck('code')->all();

        $this->assertContains('clan_joined', $codes);
        $this->assertContains('clan_leader', $codes);
        $this->assertContains('tier_e', $codes);
        $this->assertContains('tier_c', $codes);
    }

    /* ---------------------- Идемпотентность ---------------------- */

    public function test_weekly_achievement_is_not_double_paid(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);

        // Две ачивки, обе с наградой
        $codes = ['tier_a', 'tier_c'];

        foreach ($codes as $code) {
            $user->achievements()->attach(Achievement::byCode($code)->id, ['earned_at' => now()]);
        }

        $this->runCommand();

        $paid = CoinTransaction::where('source', CoinTransaction::SOURCE_ACHIEVEMENT)->count();
        $expected = $this->expectedCoins($user->fresh());

        $this->assertGreaterThanOrEqual(count($codes), $paid);
        $this->assertSame($expected, $user->fresh()->apex_coins);

        $balance = $user->fresh()->apex_coins;

        $this->runCommand();

        $this->assertSame($balance, $user->fresh()->apex_coins, 'Второй запуск не платит дважды');
        $this->assertSame($paid, CoinTransaction::where('source', CoinTransaction::SOURCE_ACHIEVEMENT)->count());
    }

    /* ---------------------- Режим проверки ---------------------- */

    public function test_dry_run_does_not_change_anything(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);
        $user->achievements()->attach(Achievement::byCode('tier_a')->id, ['earned_at' => now()]);
        $user->update(['tier_score' => 0]);

        [$exit, $output] = $this->runCommand(['--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, $user->fresh()->apex_coins, 'В режиме проверки монеты не начисляются');
        $this->assertSame(0, CoinTransaction::count());
        $this->assertStringContainsString('Режим проверки', $output);
    }

    public function test_specific_user_can_be_selected(): void
    {
        $target = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);
        $other = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);

        $target->achievements()->attach(Achievement::byCode('tier_a')->id, ['earned_at' => now()]);
        $other->achievements()->attach(Achievement::byCode('tier_a')->id, ['earned_at' => now()]);

        [$exit] = $this->runCommand(['--user' => (string) $target->id]);

        $this->assertSame(0, $exit);
        $this->assertGreaterThan(0, $target->fresh()->apex_coins);
        $this->assertSame(0, $other->fresh()->apex_coins, 'Второй игрок не тронут');
    }

    public function test_empty_database_is_successful(): void
    {
        [$exit, $output] = $this->runCommand();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('нет игроков', $output);
    }

    /* ---------------------- Выключенный источник ---------------------- */

    public function test_disabled_source_pays_nothing(): void
    {
        $user = User::factory()->create(['apex_coins' => 0, 'tier' => 'A']);
        $user->achievements()->attach(Achievement::byCode('tier_a')->id, ['earned_at' => now()]);

        // Отключаем источник наград за ачивки
        \App\Domains\Shop\Models\ShopSetting::updateOrCreate(
            ['key' => 'sources'],
            ['value' => ['achievement' => false]]
        );

        [$exit, $output] = $this->runCommand();

        $this->assertSame(0, $exit);
        $this->assertSame(0, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }
}
