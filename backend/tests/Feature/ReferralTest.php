<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Services\RewardService;
use App\Services\ShopSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Приглашения: награда пригласившему, приветственный бонус новичку, идемпотентность.
 */
class ReferralTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Origin первого-party фронтенда: без него Sanctum не включает сессию,
     * а register() дёргает $request->session()->regenerate().
     */
    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    /**
     * Регистрация через API. Email-код кладём в кэш напрямую — сам флоу
     * подтверждения почты проверяется в AuthTest.
     */
    private function register(string $username, string $email, ?string $referralCode = null): TestResponse
    {
        // Не полагаемся на SANCTUM_STATEFUL_DOMAINS из .env: явно помечаем запрос
        // как first-party, иначе сессии нет и register() падает на session()->regenerate()
        config(['sanctum.stateful' => ['localhost:5173']]);

        $token = 'verify-' . md5($email);

        Cache::put('email_verified:' . $token, $email, now()->addMinutes(30));

        $payload = [
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_token' => $token,
        ];

        if ($referralCode !== null) {
            $payload['referral_code'] = $referralCode;
        }

        return $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN])
            ->postJson('/api/auth/register', $payload);
    }

    public function test_registration_with_referral_code_pays_referrer_and_welcome_bonus(): void
    {
        $referrer = User::factory()->create([
            'username' => 'referrer_one',
            'apex_coins' => 0,
            'referral_code' => 'INVITE01',
        ]);

        $this->register('invited_one', 'invited_one@example.com', 'INVITE01')->assertCreated();

        $invited = User::where('username', 'invited_one')->firstOrFail();

        $this->assertSame($referrer->id, $invited->referred_by);
        $this->assertNotSame('INVITE01', $invited->referral_code, 'Новичок получает собственный код');
        $this->assertNotEmpty($invited->referral_code);

        $reward = (int) config('apex.coins.referral.amount');
        $welcome = (int) config('apex.coins.referral.welcome_bonus');

        $this->assertSame(200, $reward);
        $this->assertSame(50, $welcome);

        $this->assertSame($reward, $referrer->fresh()->apex_coins);
        $this->assertSame($welcome, $invited->fresh()->apex_coins);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $referrer->id,
            'source' => 'referral',
            'amount' => $reward,
            'balance_after' => $reward,
            'idempotency_key' => 'referral:' . $invited->id,
            'reference_type' => User::class,
            'reference_id' => $invited->id,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $invited->id,
            'source' => 'referral',
            'amount' => $welcome,
            'balance_after' => $welcome,
            'idempotency_key' => 'referral_welcome:' . $invited->id,
        ]);
    }

    public function test_registration_with_unknown_referral_code_pays_nothing(): void
    {
        User::factory()->create(['apex_coins' => 0, 'referral_code' => 'REALCODE']);

        $this->register('invited_two', 'invited_two@example.com', 'NOPE1234')->assertCreated();

        $invited = User::where('username', 'invited_two')->firstOrFail();

        $this->assertNull($invited->referred_by);
        $this->assertSame(0, $invited->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_registration_without_referral_code_pays_nothing(): void
    {
        $this->register('invited_three', 'invited_three@example.com')->assertCreated();

        $invited = User::where('username', 'invited_three')->firstOrFail();

        $this->assertNull($invited->referred_by);
        $this->assertSame(0, $invited->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_referral_code_is_case_sensitive(): void
    {
        $referrer = User::factory()->create(['apex_coins' => 0, 'referral_code' => 'UPPER123']);

        $this->register('invited_four', 'invited_four@example.com', 'upper123')->assertCreated();

        $invited = User::where('username', 'invited_four')->firstOrFail();

        // Текущее поведение: код ищется точным совпадением, регистр не нормализуется
        $this->assertNull($invited->referred_by);
        $this->assertSame(0, $referrer->fresh()->apex_coins);
    }

    public function test_referral_reward_is_idempotent(): void
    {
        $referrer = User::factory()->create(['apex_coins' => 0]);
        $invited = User::factory()->create(['referred_by' => $referrer->id]);

        $first = RewardService::forReferral($referrer, $invited);
        $second = RewardService::forReferral($referrer, $invited);

        $this->assertSame(200, $first);
        $this->assertSame(0, $second, 'Повторная награда за одного приглашённого не начисляется');
        $this->assertSame(200, $referrer->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::where('user_id', $referrer->id)->count());
    }

    public function test_welcome_bonus_is_idempotent(): void
    {
        $invited = User::factory()->create(['apex_coins' => 0]);

        $first = RewardService::welcomeBonus($invited);
        $second = RewardService::welcomeBonus($invited);

        $this->assertSame(50, $first);
        $this->assertSame(0, $second);
        $this->assertSame(50, $invited->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::where('user_id', $invited->id)->count());
    }

    public function test_referral_amounts_come_from_shop_settings(): void
    {
        ShopSettingService::put('referral.amount', 777);
        ShopSettingService::put('referral.welcome_bonus', 111);

        $referrer = User::factory()->create(['apex_coins' => 0]);
        $invited = User::factory()->create(['apex_coins' => 0, 'referred_by' => $referrer->id]);

        $this->assertSame(777, RewardService::forReferral($referrer, $invited));
        $this->assertSame(111, RewardService::welcomeBonus($invited));
        $this->assertSame(777, $referrer->fresh()->apex_coins);
        $this->assertSame(111, $invited->fresh()->apex_coins);
    }

    public function test_referral_rewards_are_disabled_when_source_is_off(): void
    {
        ShopSettingService::put('sources', array_merge(config('apex.coins.sources'), ['referral' => false]));

        $referrer = User::factory()->create(['apex_coins' => 0]);
        $invited = User::factory()->create(['apex_coins' => 0, 'referred_by' => $referrer->id]);

        $this->assertSame(0, RewardService::forReferral($referrer, $invited));
        $this->assertSame(0, RewardService::welcomeBonus($invited));
        $this->assertSame(0, $referrer->fresh()->apex_coins);
        $this->assertSame(0, $invited->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_wallet_shows_referral_reward_from_settings_and_recent_invites(): void
    {
        ShopSettingService::put('referral.amount', 555);

        $referrer = User::factory()->create(['apex_coins' => 0, 'referral_code' => 'SETTING1']);
        $invited = User::factory()->create(['apex_coins' => 0, 'username' => 'from_wallet', 'referred_by' => $referrer->id]);

        $response = $this->actingAs($referrer)->getJson('/api/wallet')->assertOk();

        $this->assertSame(555, $response->json('referral.reward_per_invite'));
        $this->assertSame(1, $response->json('referral.invited_count'));
        $this->assertSame('from_wallet', $response->json('referral.invited_users.0.username'));
        $this->assertSame($invited->id, $response->json('referral.invited_users.0.id'));
        $this->assertStringContainsString('ref=SETTING1', $response->json('referral.link'));
    }

    public function test_registered_user_gets_own_referral_code_usable_by_the_next_one(): void
    {
        $first = User::factory()->create(['username' => 'chain_one', 'apex_coins' => 0, 'referral_code' => 'CHAIN001']);

        $this->register('chain_two', 'chain_two@example.com', $first->referral_code)->assertCreated();

        $second = User::where('username', 'chain_two')->firstOrFail();

        $this->assertSame($first->id, $second->referred_by);
        $this->assertSame(200, $first->fresh()->apex_coins);

        // Код второго игрока тоже рабочий
        $this->register('chain_three', 'chain_three@example.com', $second->fresh()->referral_code)->assertCreated();

        $third = User::where('username', 'chain_three')->firstOrFail();

        $this->assertSame($second->id, $third->referred_by);
        // chain_two: 50 приветственных + 200 за своё приглашение
        $this->assertSame(250, $second->fresh()->apex_coins);
        $this->assertSame(50, $third->apex_coins);
    }
}
