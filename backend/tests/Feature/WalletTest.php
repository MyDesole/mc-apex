<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
use App\Models\ShopItem;
use App\Models\User;
use App\Services\CoinService;
use App\Services\ShopSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кошелёк ApexCoin: сводка, леджер, ежедневный бонус.
 *
 * Цель — зафиксировать инварианты экономики, чтобы рефакторинг не сломал их молча.
 * Тесты с суффиксом _documents_bug фиксируют ТЕКУЩЕЕ (неверное) поведение и
 * помечены комментарием BUG — при рефакторинге их нужно осознанно переписать.
 */
class WalletTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public function test_wallet_endpoints_require_authentication(): void
    {
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions')->assertUnauthorized();
        $this->postJson('/api/wallet/daily-bonus')->assertUnauthorized();
    }

    public function test_wallet_summary_reports_balance_spent_and_earned(): void
    {
        $user = $this->user(['apex_coins' => 0, 'apex_coins_spent' => 0, 'referral_code' => 'WALLET01']);

        CoinService::credit($user, 100, CoinTransaction::SOURCE_ADMIN, 'seed');
        CoinService::debit($user, 40, CoinTransaction::SOURCE_PURCHASE, 'buy');

        $response = $this->actingAs($user)->getJson('/api/wallet')->assertOk();

        $response->assertJsonStructure([
            'referral' => ['code', 'link', 'invited_count', 'reward_per_invite', 'invited_users'],
            'balance',
            'spent',
            'earned',
            'earned_by_source',
            'gifted_today',
            'daily_bonus_available',
            'daily_bonus_amount',
            'gift_limits',
            'currency',
        ]);

        $this->assertSame(60, $response->json('balance'));
        $this->assertSame(40, $response->json('spent'));
        // earned = balance + spent = все начисления за всё время
        $this->assertSame(100, $response->json('earned'));

        $this->assertSame('WALLET01', $response->json('referral.code'));
        $this->assertSame(0, $response->json('referral.invited_count'));
        $this->assertSame((int) config('apex.coins.referral.amount'), $response->json('referral.reward_per_invite'));
        $this->assertStringEndsWith('/register?ref=WALLET01', $response->json('referral.link'));

        $this->assertSame(config('apex.coins.gift'), $response->json('gift_limits'));
        $this->assertSame(config('apex.currency'), $response->json('currency'));
    }

    public function test_wallet_generates_referral_code_once_and_keeps_it(): void
    {
        $user = $this->user(['referral_code' => null]);

        $code = $this->actingAs($user)->getJson('/api/wallet')->assertOk()->json('referral.code');

        $this->assertNotEmpty($code);
        $this->assertSame($code, $user->fresh()->referral_code);

        // Повторный запрос не перегенерирует код
        $this->assertSame($code, $this->actingAs($user)->getJson('/api/wallet')->json('referral.code'));
        $this->assertSame($code, $user->fresh()->referral_code);
    }

    public function test_wallet_referral_block_lists_invited_users_and_counts_them(): void
    {
        $referrer = $this->user(['referral_code' => 'REFBLOCK1']);

        for ($i = 0; $i < 12; $i++) {
            $this->user(['username' => "invited{$i}", 'referred_by' => $referrer->id]);
        }

        $response = $this->actingAs($referrer)->getJson('/api/wallet')->assertOk();

        $this->assertSame(12, $response->json('referral.invited_count'));

        $invited = $response->json('referral.invited_users');
        $this->assertCount(10, $invited, 'В блоке приглашённых — не больше 10 последних');

        $response->assertJsonStructure([
            'referral' => [
                'invited_users' => [['id', 'username', 'avatar_url', 'joined_at']],
            ],
        ]);

        // Все 12 созданы в одну секунду, поэтому порядок «последних 10» не фиксируем —
        // проверяем только состав и уникальность
        $usernames = array_column($invited, 'username');

        $this->assertCount(10, array_unique($usernames));

        foreach ($usernames as $username) {
            $this->assertMatchesRegularExpression('/^invited\d+$/', $username);
        }
    }

    /**
     * Исправлено: earnedBySource() заканчивался на array_values(), из-за чего
     * терялись названия источников и фронтенд получал безымянный список сумм.
     * Теперь приходит карта «источник => сумма».
     */
    public function test_earned_by_source_returns_source_names(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        CoinService::credit($user, 60, CoinTransaction::SOURCE_TIER_TEST, 'tier');
        CoinService::credit($user, 40, CoinTransaction::SOURCE_ACHIEVEMENT, 'achievement');
        // Списания в «откуда монеты» не попадают
        CoinService::debit($user, 10, CoinTransaction::SOURCE_PURCHASE, 'buy');

        $bySource = $this->actingAs($user)->getJson('/api/wallet')->assertOk()->json('earned_by_source');

        $this->assertIsArray($bySource);
        $this->assertSame(60, $bySource[CoinTransaction::SOURCE_TIER_TEST] ?? null);
        $this->assertSame(40, $bySource[CoinTransaction::SOURCE_ACHIEVEMENT] ?? null);
        $this->assertArrayNotHasKey(CoinTransaction::SOURCE_PURCHASE, $bySource);
    }

    public function test_transactions_are_paginated_newest_first_and_scoped_to_owner(): void
    {
        $user = $this->user(['apex_coins' => 0]);
        $other = $this->user(['apex_coins' => 0]);

        CoinService::credit($other, 999, CoinTransaction::SOURCE_ADMIN, 'чужая операция');

        // Разводим операции по времени: created_at в тестах иначе совпадает до секунды
        foreach ([1, 2, 3, 4, 5, 6, 7] as $i) {
            $this->travel($i)->minutes();
            CoinService::credit($user, $i * 10, CoinTransaction::SOURCE_ADMIN, "op {$i}");
        }

        $this->travelBack();

        $page = $this->actingAs($user)->getJson('/api/wallet/transactions?per_page=5')->assertOk();

        $page->assertJsonStructure(['current_page', 'data', 'per_page', 'total', 'last_page', 'next_page_url']);

        $this->assertSame(7, $page->json('total'), 'В леджере видны только операции текущего игрока');
        $this->assertSame(5, $page->json('per_page'));
        $this->assertCount(5, $page->json('data'));

        $this->assertSame(
            [70, 60, 50, 40, 30],
            collect($page->json('data'))->pluck('amount')->all(),
            'История идёт от новых операций к старым'
        );

        $second = $this->actingAs($user)->getJson('/api/wallet/transactions?per_page=5&page=2')->assertOk();

        $this->assertCount(2, $second->json('data'));
        $this->assertSame([20, 10], collect($second->json('data'))->pluck('amount')->all());
    }

    public function test_transactions_per_page_is_clamped(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        CoinService::credit($user, 10, CoinTransaction::SOURCE_ADMIN, 'seed');

        $this->assertSame(5, $this->actingAs($user)->getJson('/api/wallet/transactions?per_page=1')->json('per_page'));
        $this->assertSame(100, $this->actingAs($user)->getJson('/api/wallet/transactions?per_page=1000')->json('per_page'));
        $this->assertSame(30, $this->actingAs($user)->getJson('/api/wallet/transactions')->json('per_page'));
        $this->assertSame(5, $this->actingAs($user)->getJson('/api/wallet/transactions?per_page=abc')->json('per_page'));
    }

    public function test_transactions_expose_ledger_details(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        CoinService::credit($user, 100, CoinTransaction::SOURCE_ADMIN, 'seed', 'wallet-test-key', [
            'reference_type' => ShopItem::class,
            'reference_id' => 7,
            'meta' => ['a' => 1],
        ]);

        $row = $this->actingAs($user)->getJson('/api/wallet/transactions')->assertOk()->json('data.0');

        $this->assertSame($user->id, $row['user_id']);
        $this->assertSame(100, $row['amount']);
        $this->assertSame(100, $row['balance_after']);
        $this->assertSame('admin', $row['source']);
        $this->assertSame('seed', $row['description']);
        $this->assertSame(ShopItem::class, $row['reference_type']);
        $this->assertSame(7, $row['reference_id']);
        $this->assertSame('wallet-test-key', $row['idempotency_key']);
        $this->assertSame(['a' => 1], $row['meta']);
    }

    public function test_credit_with_same_idempotency_key_is_applied_once(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        $first = CoinService::credit($user, 100, CoinTransaction::SOURCE_TIER_TEST, 'первый', 'same-key');
        $second = CoinService::credit($user, 100, CoinTransaction::SOURCE_TIER_TEST, 'повтор', 'same-key');

        $this->assertSame(100, $first);
        $this->assertSame(0, $second, 'Повторный вызов с тем же ключом идемпотентности ничего не начисляет');
        $this->assertSame(100, $user->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)->count());
    }

    public function test_ledger_balance_after_chain_matches_user_balance(): void
    {
        $user = $this->user(['apex_coins' => 0, 'apex_coins_spent' => 0]);

        CoinService::credit($user, 1000, CoinTransaction::SOURCE_ADMIN, 'seed');
        CoinService::debit($user, 300, CoinTransaction::SOURCE_PURCHASE, 'buy');
        CoinService::credit($user, 50, CoinTransaction::SOURCE_DAILY_BONUS, 'daily');

        $running = 0;

        foreach (CoinTransaction::where('user_id', $user->id)->orderBy('id')->get() as $row) {
            $running += $row->amount;

            $this->assertSame($running, $row->balance_after, 'balance_after должен идти непрерывной цепочкой');
        }

        $this->assertSame(750, $running);
        $this->assertSame($running, $user->fresh()->apex_coins);
        $this->assertSame(300, $user->fresh()->apex_coins_spent, 'apex_coins_spent копит только списания');
    }

    public function test_daily_bonus_is_paid_once_per_day_and_written_to_ledger(): void
    {
        $user = $this->user(['apex_coins' => 0]);
        $amount = (int) config('apex.coins.daily_bonus.amount');

        $first = $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertOk();

        $this->assertSame($amount, $first->json('amount'));
        $this->assertSame($amount, $first->json('balance'));
        $this->assertSame("Получено {$amount} ApexCoin за ежедневный вход.", $first->json('message'));

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'source' => 'daily_bonus',
            'amount' => $amount,
            'balance_after' => $amount,
            'idempotency_key' => 'daily_bonus:' . $user->id . ':' . today()->toDateString(),
        ]);

        $second = $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertStatus(422);

        $this->assertSame('Ежедневный бонус уже получен. Возвращайтесь завтра!', $second->json('message'));
        $this->assertSame($amount, $second->json('balance'), 'Повторная попытка не меняет баланс');

        $this->assertSame($amount, $user->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::where('user_id', $user->id)->where('source', 'daily_bonus')->count());

        $this->assertFalse($this->actingAs($user)->getJson('/api/wallet')->json('daily_bonus_available'));
    }

    public function test_daily_bonus_is_available_again_next_day(): void
    {
        $user = $this->user(['apex_coins' => 0]);
        $amount = (int) config('apex.coins.daily_bonus.amount');

        $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertOk();

        $this->travel(1)->day();

        $this->assertTrue($this->actingAs($user)->getJson('/api/wallet')->json('daily_bonus_available'));
        $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertOk();

        $this->assertSame($amount * 2, $user->fresh()->apex_coins);
        $this->assertSame(2, CoinTransaction::where('user_id', $user->id)->where('source', 'daily_bonus')->count());
    }

    public function test_daily_bonus_amount_can_come_from_shop_settings(): void
    {
        ShopSettingService::put('daily_bonus.amount', 123);

        $user = $this->user(['apex_coins' => 0]);

        $response = $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertOk();

        $this->assertSame(123, $response->json('amount'));
        $this->assertSame(123, $response->json('balance'));
    }

    public function test_wallet_preview_ignores_daily_bonus_shop_setting_documents_bug(): void
    {
        ShopSettingService::put('daily_bonus.amount', 123);

        $user = $this->user(['apex_coins' => 0]);

        $shown = $this->actingAs($user)->getJson('/api/wallet')->assertOk()->json('daily_bonus_amount');
        $paid = $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertOk()->json('amount');

        // BUG: WalletController::index отдаёт daily_bonus_amount прямо из config('apex.coins...'),
        // а CoinService::claimDailyBonus берёт сумму из shop_settings.
        // Итог: кошелёк показывает 40, а начисляется 123.
        $this->assertSame(123, $paid);
        $this->assertSame((int) config('apex.coins.daily_bonus.amount'), $shown);
        $this->assertNotSame($paid, $shown);
    }

    public function test_daily_bonus_can_be_disabled_by_config(): void
    {
        config(['apex.coins.daily_bonus.enabled' => false]);

        $user = $this->user(['apex_coins' => 0]);

        $this->assertFalse($this->actingAs($user)->getJson('/api/wallet')->json('daily_bonus_available'));

        $response = $this->actingAs($user)->postJson('/api/wallet/daily-bonus')->assertStatus(422);

        $this->assertSame('Ежедневный бонус уже получен. Возвращайтесь завтра!', $response->json('message'));
        $this->assertSame(0, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_daily_bonus_shop_setting_toggle_is_applied(): void
    {
        ShopSettingService::put('daily_bonus.enabled', false);

        $user = $this->user(['apex_coins' => 0]);

        // Исправлено: флаг читается из настроек, выключение в админке действует
        $this->assertFalse(
            $this->actingAs($user)->getJson('/api/wallet')->json('daily_bonus_available')
        );

        $this->actingAs($user)
            ->postJson('/api/wallet/daily-bonus')
            ->assertStatus(422);

        $this->assertSame(0, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }
}
