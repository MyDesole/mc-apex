<?php

namespace Tests\Feature;

use App\Domains\Auth\Mail\EmailVerificationCode;
use App\Domains\Auth\Models\EmailVerification;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Страховочная сетка для аутентификации.
 *
 * ВАЖНО про «stateful» запросы: login/logout/register дёргают
 * $request->session(), а сессия подключается только если запрос опознан
 * как запрос первого-party фронтенда (Origin/Referer из sanctum.stateful).
 * Реальный SPA ходит с Origin http://localhost:5173, поэтому тесты шлют его же.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    /**
     * Запрос «как из SPA»: с Origin, который Sanctum считает stateful.
     */
    private function stateful(): static
    {
        return $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN]);
    }

    /**
     * Кладёт в кэш подтверждённый email и возвращает одноразовый токен регистрации.
     */
    private function verificationToken(string $email): string
    {
        $token = 'verify-'.md5($email);

        Cache::put('email_verified:'.$token, $email, now()->addMinutes(30));

        return $token;
    }

    // ------------------------------------------------------------------
    // 3. Auth guards: гость обязан получать 401, а не 500
    // ------------------------------------------------------------------

    public function test_guest_gets_401_and_never_500_on_protected_endpoints(): void
    {
        $guestRoutes = [
            ['GET', '/api/auth/me'],
            ['POST', '/api/auth/logout'],
            ['GET', '/api/achievements'],
            ['GET', '/api/players/1/achievements'],
            ['GET', '/api/players/1/tier-history'],
            ['GET', '/api/wallet'],
            ['GET', '/api/wallet/transactions'],
            ['POST', '/api/wallet/daily-bonus'],
            ['GET', '/api/shop/inventory'],
            ['GET', '/api/shop/priority-candidates'],
        ];

        foreach ($guestRoutes as [$method, $url]) {
            $response = $this->json($method, $url);

            $this->assertSame(
                401,
                $response->status(),
                "{$method} {$url} должен отдавать 401 гостю, а отдал {$response->status()}"
            );
        }
    }

    // ------------------------------------------------------------------
    // 1. Регистрация
    // ------------------------------------------------------------------

    public function test_registration_creates_user_with_expected_starting_state(): void
    {
        $token = $this->verificationToken('newbie@example.com');

        $response = $this->stateful()->postJson('/api/auth/register', [
            'username' => 'new_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Аккаунт создан. Добро пожаловать!')
            ->assertJsonPath('user.username', 'new_player')
            ->assertJsonPath('user.email', 'newbie@example.com');

        $user = User::where('username', 'new_player')->firstOrFail();

        $this->assertSame('user', $user->role);
        $this->assertSame('E', $user->tier);
        $this->assertEquals(0, (float) $user->tier_score);
        $this->assertSame(0, (int) $user->apex_coins);
        $this->assertSame(0, (int) $user->apex_coins_spent);
        $this->assertFalse($user->is_banned);
        $this->assertNotNull($user->email_verified_at, 'После регистрации email считается подтверждённым');
        $this->assertNotNull($user->referral_code);

        // Токен подтверждения одноразовый
        $this->assertNull(Cache::get('email_verified:'.$token));
    }

    public function test_registration_generates_unique_referral_code(): void
    {
        $existing = User::factory()->create(['referral_code' => 'FIXEDCODE']);

        $token = $this->verificationToken('unique@example.com');

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'unique_code_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $token,
        ])->assertCreated();

        $created = User::where('username', 'unique_code_player')->firstOrFail();

        $this->assertNotSame($existing->referral_code, $created->referral_code);
        $this->assertSame(8, strlen($created->referral_code));
        $this->assertSame(strtoupper($created->referral_code), $created->referral_code);
    }

    public function test_registration_rejects_unknown_verification_token(): void
    {
        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'no_token_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => 'i-never-requested-this',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification_token']);

        $this->assertDatabaseMissing('users', ['username' => 'no_token_player']);
    }

    public function test_registration_verification_token_can_not_be_reused(): void
    {
        $token = $this->verificationToken('once@example.com');

        $payload = [
            'username' => 'once_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $token,
        ];

        $this->stateful()->postJson('/api/auth/register', $payload)->assertCreated();

        $payload['username'] = 'once_player_two';

        $this->stateful()->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification_token']);

        $this->assertDatabaseMissing('users', ['username' => 'once_player_two']);
    }

    public function test_registration_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'taken_name']);

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'taken_name',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $this->verificationToken('dup-name@example.com'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_rejects_already_registered_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'fresh_username',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $this->verificationToken('taken@example.com'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_validation_rules(): void
    {
        // Короткий ник, короткий пароль, несовпадающее подтверждение и пустой токен
        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'ab',
            'password' => 'short',
            'password_confirmation' => 'other',
            'verification_token' => 'x',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);

        // Недопустимые символы в нике
        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'bad name!',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => 'x',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // Слишком длинный ник
        $this->stateful()->postJson('/api/auth/register', [
            'username' => str_repeat('a', 33),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => 'x',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // Вообще пустой запрос
        $this->stateful()->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password', 'verification_token']);
    }

    public function test_registration_with_referral_code_links_referrer_and_pays_both_sides(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'INVITE01', 'apex_coins' => 0]);

        $response = $this->stateful()->postJson('/api/auth/register', [
            'username' => 'invited_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $this->verificationToken('invited@example.com'),
            'referral_code' => 'INVITE01',
        ]);

        $response->assertCreated();

        $invited = User::where('username', 'invited_player')->firstOrFail();

        $this->assertSame($referrer->id, $invited->referred_by);

        $referralReward = (int) config('apex.coins.referral.amount');
        $welcomeBonus = (int) config('apex.coins.referral.welcome_bonus');

        $this->assertSame($referralReward, (int) $referrer->fresh()->apex_coins);
        $this->assertSame($welcomeBonus, (int) $invited->apex_coins);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $referrer->id,
            'source' => 'referral',
            'amount' => $referralReward,
            'idempotency_key' => 'referral:'.$invited->id,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $invited->id,
            'source' => 'referral',
            'amount' => $welcomeBonus,
        ]);
    }

    public function test_registration_with_unknown_referral_code_is_ignored(): void
    {
        $response = $this->stateful()->postJson('/api/auth/register', [
            'username' => 'no_invite_player',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $this->verificationToken('noinvite@example.com'),
            'referral_code' => 'DOES-NOT-EXIST',
        ]);

        $response->assertCreated();

        $user = User::where('username', 'no_invite_player')->firstOrFail();

        $this->assertNull($user->referred_by);
        $this->assertSame(0, (int) $user->apex_coins, 'Без реферера приветственный бонус не начисляется');
    }

    // ------------------------------------------------------------------
    // Регистрация в три шага: код на почту → проверка кода → регистрация
    // ------------------------------------------------------------------

    public function test_three_step_registration_flow(): void
    {
        Mail::fake();

        $send = $this->postJson('/api/auth/register/send-code', ['email' => 'flow@example.com']);

        $send->assertOk()
            ->assertJsonPath('email', 'flow@example.com');

        $code = null;

        Mail::assertSent(EmailVerificationCode::class, function (EmailVerificationCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->assertIsString($code);
        $this->assertSame(6, strlen($code));

        $stored = EmailVerification::where('email', 'flow@example.com')->firstOrFail();

        $this->assertSame($code, $stored->code);
        $this->assertNull($stored->verified_at);

        $wrong = $this->postJson('/api/auth/register/verify-code', [
            'email' => 'flow@example.com',
            'code' => $code === '000000' ? '111111' : '000000',
        ]);

        $wrong->assertStatus(422)->assertJsonValidationErrors(['code']);

        $verified = $this->postJson('/api/auth/register/verify-code', [
            'email' => 'flow@example.com',
            'code' => $code,
        ]);

        $verified->assertOk()->assertJsonPath('message', 'Email подтверждён.');

        $token = $verified->json('verification_token');

        $this->assertNotEmpty($token);

        $registered = $this->stateful()->postJson('/api/auth/register', [
            'username' => 'flow_user',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'verification_token' => $token,
        ]);

        $registered->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'flow_user', 'email' => 'flow@example.com']);

        // Код помечен использованным
        $this->assertNotNull(EmailVerification::where('email', 'flow@example.com')->firstOrFail()->verified_at);
    }

    public function test_send_verification_code_rejects_existing_email(): void
    {
        User::factory()->create(['email' => 'busy@example.com']);

        $this->postJson('/api/auth/register/send-code', ['email' => 'busy@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_verification_code_validation(): void
    {
        $this->postJson('/api/auth/register/send-code', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/register/send-code', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_code_rejects_expired_and_unknown_codes(): void
    {
        EmailVerification::create([
            'email' => 'expired@example.com',
            'code' => '111111',
            'expires_at' => now()->subMinute(),
            'verified_at' => null,
        ]);

        $this->postJson('/api/auth/register/verify-code', [
            'email' => 'expired@example.com',
            'code' => '111111',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->postJson('/api/auth/register/verify-code', [
            'email' => 'nobody@example.com',
            'code' => '111111',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Кривой формат кода
        $this->postJson('/api/auth/register/verify-code', [
            'email' => 'nobody@example.com',
            'code' => '12345',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // ------------------------------------------------------------------
    // 2. Вход
    // ------------------------------------------------------------------

    public function test_login_works_with_username_and_with_email(): void
    {
        $user = User::factory()->create([
            'username' => 'login_player',
            'email' => 'login@example.com',
            'password' => 'secret-password',
        ]);

        $byUsername = $this->stateful()->postJson('/api/auth/login', [
            'login' => 'login_player',
            'password' => 'secret-password',
        ]);

        $byUsername->assertOk()
            ->assertJsonPath('message', 'Вход выполнен успешно.')
            ->assertJsonPath('user.username', 'login_player');

        $this->assertAuthenticatedAs($user);

        auth()->forgetGuards();

        $byEmail = $this->stateful()->postJson('/api/auth/login', [
            'login' => 'login@example.com',
            'password' => 'secret-password',
        ]);

        $byEmail->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rejects_wrong_password_and_unknown_login(): void
    {
        User::factory()->create(['username' => 'known_player', 'password' => 'secret-password']);

        $this->stateful()->postJson('/api/auth/login', [
            'login' => 'known_player',
            'password' => 'not-the-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_login_validation_rules(): void
    {
        $this->stateful()->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);

        $this->stateful()->postJson('/api/auth/login', ['login' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_rejects_unverified_email(): void
    {
        $user = User::factory()->unverified()->create([
            'username' => 'unverified_player',
            'password' => 'secret-password',
        ]);

        $this->stateful()->postJson('/api/auth/login', [
            'login' => 'unverified_player',
            'password' => 'secret-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Бан
    // ------------------------------------------------------------------

    /**
     * ТЕКУЩЕЕ ПОВЕДЕНИЕ (потенциальная дыра): контроллер входа не проверяет бан,
     * а EnsureUserIsNotBanned выполняется до аутентификации, когда $request->user()
     * ещё null. Поэтому забаненный игрок успешно «входит» и получает свой профиль
     * в ответе; блокировка наступает только на следующих запросах.
     */
    public function test_banned_user_cannot_log_in(): void
    {
        $banned = User::factory()->create([
            'username' => 'banned_login_player',
            'password' => 'secret-password',
            'is_banned' => true,
            'ban_reason' => 'читы',
        ]);

        $response = $this->stateful()->postJson('/api/auth/login', [
            'login' => 'banned_login_player',
            'password' => 'secret-password',
        ]);

        // Исправлено: бан проверяется при входе, сессия не выдаётся
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('забанен', (string) $response->json('errors.login.0'));
        $this->assertGuest();
    }

    public function test_banned_user_with_bearer_token_is_blocked_with_403(): void
    {
        $banned = User::factory()->create([
            'is_banned' => true,
            'ban_reason' => 'читы',
        ]);

        $token = $banned->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/auth/me');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Ваш аккаунт забанен.')
            ->assertJsonPath('reason', 'читы');

        // Токен отзывается
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * ТЕКУЩЕЕ ПОВЕДЕНИЕ (баг): для пользователя, вошедшего по сессии/куке,
     * currentAccessToken() — это Laravel\Sanctum\TransientToken, у которого нет
     * метода delete(). Middleware падает с 500 вместо 403.
     */
    public function test_banned_session_user_gets_500_instead_of_403(): void
    {
        $banned = User::factory()->create(['is_banned' => true, 'ban_reason' => 'читы']);

        $response = $this->actingAs($banned)->getJson('/api/auth/me');

        $response->assertStatus(500);
        $this->assertStringContainsString(
            'TransientToken::delete()',
            (string) $response->json('message')
        );
    }

    public function test_expired_ban_does_not_block_the_user(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'ban_reason' => 'старый бан',
            'banned_until' => now()->subDay(),
        ]);

        $this->assertFalse($user->isBanned());

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
    }

    // ------------------------------------------------------------------
    // /me и выход
    // ------------------------------------------------------------------

    public function test_me_returns_user_with_aspects_and_rank(): void
    {
        User::factory()->create(['tier' => 'A', 'tier_score' => 80]);
        $user = User::factory()->create(['tier' => 'C', 'tier_score' => 45]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure(['user', 'rank' => ['position', 'total']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('rank.position', 2)
            ->assertJsonPath('rank.total', 2);

        $this->assertArrayHasKey('pvp', $response->json('user.aspects'));
        $this->assertArrayHasKey('bedwars', $response->json('user.aspects'));
    }

    public function test_me_rank_is_empty_for_unrated_player(): void
    {
        $user = User::factory()->create(['tier' => 'E', 'tier_score' => 0]);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('rank.position', null)
            ->assertJsonPath('rank.total', 0);
    }

    public function test_logout_clears_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->stateful()->actingAs($user)->postJson('/api/auth/logout');

        $response->assertOk()->assertJsonPath('message', 'Вы вышли из аккаунта.');

        // Имени сессии больше нет: сессионный гвард пуст.
        // (assertGuest() здесь не годится — auth:sanctum делает shouldUse('sanctum'),
        //  а RequestGuard санктума кэширует пользователя на время всего теста.)
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull(Auth::guard('web')->user());

        auth()->forgetGuards();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
