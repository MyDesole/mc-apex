<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\EmailVerification;
use App\Domains\Auth\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationEmailPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_disposable_domains_and_subdomains_cannot_request_codes(): void
    {
        Mail::fake();
        foreach (['euploading.com', 'TANPONY.COM', 'ooynib.com', 'bwmyga.com', 'gmeenramy.com', 'mail.euploading.com', 'mailinator.com'] as $domain) {
            Cache::flush();
            $this->postJson('/api/auth/register/send-code', ['email' => 'guard@'.$domain])
                ->assertUnprocessable()->assertJsonValidationErrors('email')
                ->assertJsonPath('errors.email.0', 'Регистрация с временной почтой запрещена. Используйте постоянный почтовый адрес.');
        }
        $this->assertDatabaseCount('email_verifications', 0);
        Mail::assertNothingSent();
    }

    public function test_existing_codes_and_previously_verified_tokens_cannot_bypass_the_block(): void
    {
        EmailVerification::create(['email' => 'old@euploading.com', 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/auth/register/verify-code', ['email' => 'old@euploading.com', 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        Cache::put('email_verified:old-blocked-token', 'old@euploading.com', 600);
        $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/auth/register', [
            'username' => 'BlockedBot', 'password' => 'password123', 'password_confirmation' => 'password123', 'verification_token' => 'old-blocked-token',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseMissing('users', ['username' => 'BlockedBot']);
        $this->assertNull(Cache::get('email_verified:old-blocked-token'));
    }

    public function test_regular_email_still_completes_registration(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/register/send-code', ['email' => 'real.player@gmail.com'])->assertOk();
        $code = EmailVerification::where('email', 'real.player@gmail.com')->value('code');
        $verification = $this->postJson('/api/auth/register/verify-code', ['email' => 'real.player@gmail.com', 'code' => $code])->assertOk();
        $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/auth/register', [
            'username' => 'RealPlayer', 'password' => 'password123', 'password_confirmation' => 'password123', 'verification_token' => $verification->json('verification_token'),
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'RealPlayer', 'email' => 'real.player@gmail.com']);
        Mail::assertSentCount(1);
    }

    public function test_a_lookalike_suffix_is_not_blocked(): void
    {
        $code = app(EmailVerificationService::class)->issueCode('player@euploading.com.example.org');
        $this->assertSame(6, strlen($code));
        $this->assertDatabaseHas('email_verifications', ['email' => 'player@euploading.com.example.org']);
    }
}
