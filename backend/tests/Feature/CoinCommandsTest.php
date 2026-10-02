<?php

namespace Tests\Feature;

use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Artisan-команда apex:coins — выдача и списание ApexCoin из консоли.
 *
 * Текст вывода проверяем через Artisan::call(...) с BufferedOutput:
 * PendingCommand с несколькими expectsOutputToContain() съедает строку
 * только первой подходящей проверкой, если она попала в один doWrite.
 */
class CoinCommandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runCommand(array $parameters): array
    {
        $buffer = new BufferedOutput;

        $exitCode = Artisan::call('apex:coins', $parameters, $buffer);

        return [$exitCode, $buffer->fetch()];
    }

    public function test_apex_coins_without_amount_shows_balance(): void
    {
        $user = User::factory()->create([
            'username' => 'balance_guy',
            'apex_coins' => 4242,
            'apex_coins_spent' => 99,
        ]);

        [$exitCode, $output] = $this->runCommand(['user' => (string) $user->id]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('balance_guy', $output);
        $this->assertStringContainsString('4242', $output);
        $this->assertStringContainsString('99', $output);

        // Показ баланса ничего не меняет
        $this->assertSame(4242, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_all_without_amount_shows_totals(): void
    {
        User::factory()->create(['apex_coins' => 100, 'apex_coins_spent' => 10]);
        User::factory()->create(['apex_coins' => 200, 'apex_coins_spent' => 20]);

        [$exitCode, $output] = $this->runCommand(['user' => 'all']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('300', $output);
        $this->assertStringContainsString('30', $output);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_credits_one_user_and_writes_ledger(): void
    {
        $user = User::factory()->create(['apex_coins' => 100]);

        [$exitCode, $output] = $this->runCommand([
            'user' => (string) $user->id,
            'amount' => '250',
            '--reason' => 'тестовая выдача',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('+250', $output);
        $this->assertStringContainsString('тестовая выдача', $output);

        $this->assertSame(350, $user->fresh()->apex_coins);
        $this->assertSame(0, $user->fresh()->apex_coins_spent);

        $tx = CoinTransaction::where('user_id', $user->id)->sole();

        $this->assertSame(250, $tx->amount);
        $this->assertSame(350, $tx->balance_after);
        $this->assertSame('admin', $tx->source);
        $this->assertSame('тестовая выдача', $tx->description);
        $this->assertSame(['via' => 'artisan apex:coins'], $tx->meta);
        $this->assertNull($tx->idempotency_key);
        $this->assertNull($tx->actor_id);
    }

    public function test_apex_coins_uses_default_reason_when_not_given(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $this->artisan('apex:coins', ['user' => (string) $user->id, 'amount' => '10'])->assertExitCode(0);

        $this->assertSame('Начисление из консоли', CoinTransaction::where('user_id', $user->id)->sole()->description);
    }

    public function test_apex_coins_remove_flag_debits_and_counts_spent(): void
    {
        $user = User::factory()->create(['apex_coins' => 500]);

        $this->artisan('apex:coins', [
            'user' => (string) $user->id,
            'amount' => '100',
            '--remove' => true,
        ])->assertExitCode(0);

        $this->assertSame(400, $user->fresh()->apex_coins);
        $this->assertSame(100, $user->fresh()->apex_coins_spent);

        $tx = CoinTransaction::where('user_id', $user->id)->sole();

        $this->assertSame(-100, $tx->amount);
        $this->assertSame(400, $tx->balance_after);
        $this->assertSame('Списание из консоли', $tx->description);
    }

    public function test_apex_coins_understands_negative_amount_argument(): void
    {
        $user = User::factory()->create(['apex_coins' => 100]);

        $this->artisan('apex:coins', ['user' => (string) $user->id, 'amount' => '-40'])->assertExitCode(0);

        $this->assertSame(60, $user->fresh()->apex_coins);
        $this->assertSame(-40, CoinTransaction::where('user_id', $user->id)->sole()->amount);
    }

    public function test_apex_coins_rejects_zero_amount(): void
    {
        $user = User::factory()->create(['apex_coins' => 100]);

        $this->artisan('apex:coins', ['user' => (string) $user->id, 'amount' => '0'])->assertExitCode(1);

        $this->assertSame(100, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_rejects_unknown_user(): void
    {
        $this->artisan('apex:coins', ['user' => '999999', 'amount' => '100'])->assertExitCode(1);

        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_rejects_non_numeric_target(): void
    {
        $this->artisan('apex:coins', ['user' => 'nikita', 'amount' => '100'])->assertExitCode(1);

        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_does_not_allow_overdraft(): void
    {
        $user = User::factory()->create(['apex_coins' => 50]);

        $this->artisan('apex:coins', [
            'user' => (string) $user->id,
            'amount' => '100',
            '--remove' => true,
        ])->assertExitCode(1);

        $this->assertSame(50, $user->fresh()->apex_coins);
        $this->assertSame(0, $user->fresh()->apex_coins_spent);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_all_declined_confirmation_changes_nothing(): void
    {
        $users = User::factory()->count(3)->create(['apex_coins' => 0]);

        $this->artisan('apex:coins', ['user' => 'all', 'amount' => '100'])
            ->expectsConfirmation('Продолжить?', 'no')
            ->assertExitCode(1);

        foreach ($users as $user) {
            $this->assertSame(0, $user->fresh()->apex_coins);
        }

        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_apex_coins_all_accepted_confirmation_credits_everyone(): void
    {
        $users = User::factory()->count(3)->create(['apex_coins' => 0]);

        $this->artisan('apex:coins', ['user' => 'all', 'amount' => '100'])
            ->expectsConfirmation('Продолжить?', 'yes')
            ->assertExitCode(0);

        foreach ($users as $user) {
            $this->assertSame(100, $user->fresh()->apex_coins);
        }

        $this->assertSame(3, CoinTransaction::count());
    }

    public function test_apex_coins_all_with_force_skips_confirmation(): void
    {
        $users = User::factory()->count(2)->create(['apex_coins' => 10]);

        $this->artisan('apex:coins', [
            'user' => 'all',
            'amount' => '5',
            '--force' => true,
            '--reason' => 'массовая выдача',
        ])->assertExitCode(0);

        foreach ($users as $user) {
            $this->assertSame(15, $user->fresh()->apex_coins);
        }

        $this->assertSame(2, CoinTransaction::where('description', 'массовая выдача')->count());
    }

    public function test_apex_coins_all_skips_players_who_cannot_pay_and_keeps_going(): void
    {
        $rich = User::factory()->create(['apex_coins' => 100]);
        $poor = User::factory()->create(['apex_coins' => 10]);

        [$exitCode, $output] = $this->runCommand([
            'user' => 'all',
            'amount' => '50',
            '--remove' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Начислено: 1, пропущено с ошибкой: 1.', $output);

        $this->assertSame(50, $rich->fresh()->apex_coins);
        $this->assertSame(10, $poor->fresh()->apex_coins, 'Игрок, которому не хватает монет, пропускается');
        $this->assertSame(1, CoinTransaction::count());
    }

    public function test_apex_coins_all_with_empty_database_is_successful(): void
    {
        [$exitCode, $output] = $this->runCommand(['user' => 'all', 'amount' => '100', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('В базе нет игроков.', $output);
    }
}
