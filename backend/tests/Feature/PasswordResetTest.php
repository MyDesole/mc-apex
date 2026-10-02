<?php

namespace Tests\Feature;

use App\Domains\Auth\Mail\PasswordResetCode;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    /**
     * Кладём в таблицу сброса известный код, чтобы тест не зависел от письма.
     */
    private function seedResetCode(User $user, string $code = '123456', ?Carbon $createdAt = null): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($code),
                'created_at' => $createdAt ?? now(),
            ]
        );
    }

    private function resetPayload(User $user, string $code, array $overrides = []): array
    {
        return array_merge([
            'email' => $user->email,
            'code' => $code,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Шаг 1: запрос кода
    // ------------------------------------------------------------------

    public function test_forgot_password_for_unknown_email_answers_the_same_and_sends_nothing(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk()
            ->assertJsonPath('message', 'Если такой email зарегистрирован, мы отправили на него код.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_sends_a_six_digit_code_for_known_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);

        $response->assertOk()
            ->assertJsonPath('message', 'Если такой email зарегистрирован, мы отправили на него код.');

        $code = null;

        Mail::assertSent(PasswordResetCode::class, function (PasswordResetCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->assertIsString($code);
        $this->assertSame(6, strlen($code));

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($record);
        $this->assertTrue(Hash::check($code, $record->token), 'Код в БД должен быть захеширован и совпадать с письмом');
    }

    public function test_forgot_password_validation(): void
    {
        $this->postJson('/api/auth/forgot-password', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_throttled_after_three_requests(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/auth/forgot-password', ['email' => "user{$i}@example.com"])
                ->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'user4@example.com'])
            ->assertStatus(429);
    }

    // ------------------------------------------------------------------
    // Шаг 2: смена пароля по коду
    // ------------------------------------------------------------------

    public function test_reset_password_with_valid_code_changes_password_and_consumes_the_code(): void
    {
        $user = User::factory()->create([
            'email' => 'reset2@example.com',
            'username' => 'reset_player',
            'password' => 'old-password',
        ]);

        $this->seedResetCode($user, '654321');

        $response = $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '654321'));

        $response->assertOk()->assertJsonPath('message', 'Пароль обновлён. Теперь можно войти.');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));

        $this->assertDatabaseCount('password_reset_tokens', 0);

        // Новым паролем действительно можно войти
        $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN])
            ->postJson('/api/auth/login', [
                'login' => 'reset_player',
                'password' => 'brand-new-password',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_reset_password_rejects_wrong_code(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->seedResetCode($user, '111111');

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '222222'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_reset_password_rejects_account_without_a_token(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '123456'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_password_validation(): void
    {
        $user = User::factory()->create();

        // Короткий код и невалидный email
        $this->postJson('/api/auth/reset-password', [
            'email' => 'not-an-email',
            'code' => '123',
            'password' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'code', 'password']);

        // Пароль короче 8 символов и без подтверждения
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_code_can_not_be_reused(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->seedResetCode($user, '424242');

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '424242'))->assertOk();

        // Запись удалена — тот же код больше не принимается
        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '424242'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    /**
     * Исправлено: проверка срока сравнивала знаковую разницу
     * (now()->diffInMinutes($created_at) > 15), из-за чего просроченный код
     * принимался. Теперь код старше 15 минут отклоняется.
     */
    public function test_expired_reset_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $this->seedResetCode($user, '999999', now()->subMinutes(20));

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '999999'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password),
            'Пароль не должен меняться по просроченному коду'
        );

        // Код суточной давности тоже не работает
        $this->seedResetCode($user, '888888', now()->subDay());

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '888888'))
            ->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    /**
     * Граница: код младше 15 минут по-прежнему принимается.
     */
    public function test_fresh_reset_code_is_still_accepted(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->seedResetCode($user, '777777', now()->subMinutes(10));

        $this->postJson('/api/auth/reset-password', $this->resetPayload($user, '777777'))
            ->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }
}
