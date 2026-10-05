<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\BridgeTechniqueVariant;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Куратор бриджа как тестер, история проверок и подвиды заявки.
 */
class BridgeCuratorTesterTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';


    private function service(): BridgeService
    {
        return app(BridgeService::class);
    }

    private function technique(string $label = 'Telly Bridge'): BridgeTechnique
    {
        return BridgeTechnique::create([
            'key' => strtolower(str_replace(' ', '_', $label)),
            'label' => $label,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function variant(BridgeTechnique $technique, string $label, bool $special = false): BridgeTechniqueVariant
    {
        return BridgeTechniqueVariant::create([
            'technique_id' => $technique->id,
            'key' => strtolower(str_replace(' ', '_', $label)),
            'label' => $label,
            'is_special' => $special,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /** Создаёт заявку через сервис, минуя загрузку видео. */
    private function submission(User $player, BridgeTechnique $technique, array $variants = []): UserBridgeTechnique
    {
        return $this->service()->declare(
            user: $player,
            techniqueId: $technique->id,
            videoUrl: 'https://example.test/video',
            uploadId: null,
            variants: $variants,
        );
    }

    /* ---------------- Куратор как тестер ---------------- */

    public function test_curator_sees_pending_queue(): void
    {
        $player = User::factory()->create();
        $curator = User::factory()->create(['role' => 'bridge_curator']);

        $this->submission($player, $this->technique());

        Sanctum::actingAs($curator);

        $this->getJson('/api/bridge-review/submissions')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $player->id);
    }

    public function test_curator_can_conduct_a_test(): void
    {
        $player = User::factory()->create();
        $curator = User::factory()->create(['role' => 'bridge_curator']);

        $submission = $this->submission($player, $this->technique());

        Sanctum::actingAs($curator);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => true,
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('submission.status', 'confirmed')
            ->assertJsonPath('submission.reviewer.username', $curator->username);
    }

    public function test_curator_sees_tests_conducted_by_other_testers(): void
    {
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $curator = User::factory()->create(['role' => 'bridge_curator']);

        $first = $this->submission($playerA, $this->technique('Telly'));
        $second = $this->submission($playerB, $this->technique('Moonwalk'));

        $this->service()->confirm($first, $tester, [
            'stability' => 40, 'speed' => 50, 'difficulty' => 60, 'score' => 5,
        ]);

        $this->service()->reject($second, $tester, 'Плохое качество');

        Sanctum::actingAs($curator);

        $response = $this->getJson('/api/bridge-review/history')->assertOk();

        $this->assertCount(2, $response->json('data'));

        // Видно, кто именно проверял
        $reviewers = array_column($response->json('data'), 'reviewer');

        $this->assertSame($tester->username, $reviewers[0]['username']);
    }

    public function test_regular_player_cannot_see_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/bridge-review/history')->assertForbidden();
    }

    public function test_bridge_tester_can_see_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'bridge_tester']));

        $this->getJson('/api/bridge-review/history')->assertOk();
    }

    /* ---------------- Подвиды заявки ---------------- */

    public function test_player_declares_variants_on_submission(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $special = $this->variant($technique, 'С удержанием', true);
        $plain = $this->variant($technique, 'На 1.8');

        $submission = $this->submission($player, $technique, [$special->id, $plain->id]);

        $this->assertSame([$special->id, $plain->id], $submission->variants);
    }

    public function test_player_toggles_variants(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $first = $this->variant($technique, 'Первый');
        $second = $this->variant($technique, 'Второй');

        $submission = $this->submission($player, $technique, [$first->id]);

        Sanctum::actingAs($player);

        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'submission.variants');

        // И обратно — выключаем
        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [],
        ])->assertOk()->assertJsonCount(0, 'submission.variants');
    }

    public function test_tester_toggles_variants(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $variant = $this->variant($technique, 'С удержанием', true);

        $submission = $this->submission($player, $technique);

        Sanctum::actingAs($tester);

        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [$variant->id],
        ])
            ->assertOk()
            ->assertJsonPath('submission.variants.0.is_special', true);
    }

    public function test_player_cannot_toggle_variants_after_confirmation(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->submission($player, $technique);

        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        Sanctum::actingAs($player);

        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [],
        ])->assertStatus(422);
    }

    public function test_tester_can_still_change_variants_after_confirmation(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $variant = $this->variant($technique, 'С удержанием', true);

        $submission = $this->submission($player, $technique);

        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        Sanctum::actingAs($tester);

        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [$variant->id],
        ])->assertOk()->assertJsonCount(1, 'submission.variants');
    }

    public function test_variant_of_another_technique_is_ignored(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique('Telly');
        $other = $this->technique('Moonwalk');

        $mine = $this->variant($technique, 'Мой');
        $foreign = $this->variant($other, 'Чужой');

        $submission = $this->submission($player, $technique, [$mine->id, $foreign->id]);

        $this->assertSame([$mine->id], $submission->variants);
    }

    public function test_another_player_cannot_toggle_variants(): void
    {
        $player = User::factory()->create();
        $other = User::factory()->create();
        $technique = $this->technique();

        $submission = $this->submission($player, $technique);

        Sanctum::actingAs($other);

        $this->putJson("/api/bridge/submissions/{$submission->id}/variants", [
            'variants' => [],
        ])->assertForbidden();
    }

    public function test_tester_confirms_variants_during_review(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $variant = $this->variant($technique, 'С удержанием', true);

        $submission = $this->submission($player, $technique);

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => true,
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
            'variants' => [$variant->id],
        ])
            ->assertOk()
            ->assertJsonPath('submission.variants.0.label', 'С удержанием');
    }

    /* ---------------- Особый подвид ---------------- */

    public function test_curator_marks_variant_as_special(): void
    {
        $curator = User::factory()->create(['role' => 'bridge_curator']);
        $technique = $this->technique();
        $variant = $this->variant($technique, 'С удержанием');

        Sanctum::actingAs($curator);

        $this->putJson("/api/bridge-curator/variants/{$variant->id}", [
            'is_special' => true,
        ])
            ->assertOk()
            ->assertJsonPath('variant.is_special', true);
    }

    public function test_special_flag_reaches_player_catalog(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $this->variant($technique, 'С удержанием', true);

        Sanctum::actingAs($player);

        $this->getJson('/api/bridge/techniques')
            ->assertOk()
            ->assertJsonPath('data.0.technique.variants.0.is_special', true);
    }

    /** Запрос как с фронтенда: иначе session() недоступна в контроллере. */
    private function stateful(): static
    {
        return $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN]);
    }

    /** Кладёт токен подтверждения в кеш: так его выдаёт send-code. */
    private function verifiedToken(string $email, string $token): void
    {
        \Illuminate\Support\Facades\Cache::put(
            'email_verified:' . $token,
            $email,
            now()->addHour(),
        );
    }

    /* ---------------- Регистрация ---------------- */

    public function test_register_with_bridge_mode(): void
    {
        $this->verifiedToken('newbridger@example.test', 'test-token-bridge');

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'NewBridger',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_token' => 'test-token-bridge',
            'profile_mode' => 'bridge',
        ])->assertCreated();

        $this->assertSame(
            'bridge',
            User::query()->where('username', 'NewBridger')->first()->profile_mode,
        );
    }

    public function test_register_defaults_to_pvp_mode(): void
    {
        $this->verifiedToken('newpvp@example.test', 'test-token-pvp');

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'NewPvp',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_token' => 'test-token-pvp',
        ])->assertCreated();

        $this->assertSame(
            'pvp',
            User::query()->where('username', 'NewPvp')->first()->profile_mode,
        );
    }

    public function test_unknown_profile_mode_is_rejected_on_register(): void
    {
        $this->verifiedToken('bad@example.test', 'test-token-bad');

        $this->stateful()->postJson('/api/auth/register', [
            'username' => 'BadMode',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_token' => 'test-token-bad',
            'profile_mode' => 'bedwars',
        ])->assertStatus(422)->assertJsonValidationErrors(['profile_mode']);
    }
}
