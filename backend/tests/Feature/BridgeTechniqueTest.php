<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Notifications\BridgeSubmissionNotification;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Бридж-тесты: заявки видов бриджа и проверка тестером.
 *
 * Механика отличается от обычных тир-тестов: игрок не встаёт в очередь, а
 * отмечает вид и прикладывает видео. Пока бридж-тестер не подтвердил, вид
 * в профиле серый и в топ не попадает.
 */
class BridgeTechniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
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

/** Прогоняет чанковую загрузку и возвращает upload_id. */
    private function uploadVideo(User $user, int $size = 1024): string
    {
        Sanctum::actingAs($user);

        $init = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => $size,
            'mime' => 'video/mp4',
        ])->assertCreated();

        $uuid = $init->json('uuid');

        $this->post(
            "/api/bridge/uploads/{$uuid}/chunks",
            [
                'index' => 0,
                'chunk' => UploadedFile::fake()->createWithContent('part.mp4', str_repeat('A', $size)),
            ],
            ['Accept' => 'application/json'],
        )->assertOk();

        $this->postJson("/api/bridge/uploads/{$uuid}/complete")->assertOk();

        return $uuid;
    }

    private function service(): BridgeService
    {
        return app(BridgeService::class);
    }

    public function test_player_declares_technique_with_video(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])
            ->assertCreated()
            ->assertJsonPath('submission.status', 'declared')
            ->assertJsonPath('submission.has_video', true);

        $this->assertDatabaseHas('user_bridge_techniques', [
            'user_id' => $player->id,
            'technique_id' => $technique->id,
            'status' => 'declared',
        ]);
    }

    public function test_video_is_required(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['upload_id']);

        $this->assertSame(0, UserBridgeTechnique::query()->count());
    }

    public function test_catalog_shows_all_techniques_with_my_state(): void
    {
        $player = User::factory()->create();
        $first = $this->technique('Speed Bridge');
        $this->technique('Moonwalk');

        Sanctum::actingAs($player);

        $uploadId = $this->uploadVideo($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $first->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $response = $this->getJson('/api/bridge/techniques')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('data.0.submission.status', 'declared');
        $response->assertJsonPath('data.1.submission', null);
    }

    public function test_confirmed_technique_counts_in_summary(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        $this->service()->confirm($submission, $tester, [
            'stability' => 50,
            'speed' => 70,
            'difficulty' => 70,
            'score' => 8,
        ]);

        $summary = $this->service()->summary($player);

        $this->assertSame(1, $summary['confirmed_count']);
        $this->assertSame(190, $summary['aspects_total'], 'Сумма аспектов 50+70+70');
        $this->assertSame(8, $summary['score_total']);
    }

    public function test_total_is_sum_of_three_aspects(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        $confirmed = $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        $this->assertSame(190, $confirmed->total);
        $this->assertSame(300, UserBridgeTechnique::MAX_ASPECT * 3);
    }

    public function test_regular_player_cannot_see_the_review_panel(): void
    {
        $player = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($player);

        $this->getJson('/api/bridge-review/submissions')->assertForbidden();
    }

    public function test_regular_tester_cannot_see_the_bridge_panel(): void
    {
        $tester = User::factory()->create(['role' => 'tester']);

        Sanctum::actingAs($tester);

        $this->getJson('/api/bridge-review/submissions')->assertForbidden();
    }

    public function test_bridge_tester_sees_pending_submissions(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($tester);

        $response = $this->getJson('/api/bridge-review/submissions')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'declared');
        $response->assertJsonPath('data.0.user.id', $player->id);
    }

    public function test_admin_can_review(): void
    {
        $player = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($admin);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => true,
            'stability' => 50,
            'speed' => 70,
            'difficulty' => 70,
            'score' => 8,
        ])->assertOk()->assertJsonPath('submission.status', 'confirmed');
    }

    public function test_reject_keeps_technique_hidden(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        $this->service()->reject($submission, $tester, 'Не видно кпс мод');

        $this->assertSame(0, $this->service()->summary($player)['confirmed_count']);
        $this->assertSame(
            'rejected',
            $submission->fresh()->status
        );
    }

    public function test_rejected_player_can_resubmit(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/old');
        $this->service()->reject($submission, $tester, 'Плохое качество');

        $again = $this->service()->declare($player, $technique->id, 'https://youtu.be/new');

        $this->assertSame('declared', $again->status);
        $this->assertSame('https://youtu.be/new', $again->video_url);
    }

    public function test_confirmed_technique_cannot_be_declared_again(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');
        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);

        $this->service()->declare($player, $technique->id, 'https://youtu.be/again');
    }

    public function test_review_requires_scores_when_confirming(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['stability', 'speed', 'difficulty', 'score']);
    }

    public function test_cannot_review_twice(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);

        $this->service()->confirm($submission->fresh(), $tester, [
            'stability' => 10, 'speed' => 10, 'difficulty' => 10, 'score' => 1,
        ]);
    }

    public function test_bridge_ranking_orders_by_techniques_then_total(): void
    {
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        $one = User::factory()->create(['username' => 'OneTech']);
        $two = User::factory()->create(['username' => 'TwoTech']);

        // У первого один вид, но высокие оценки
        foreach ([['A', 90, 90, 90, 10]] as [$label, $st, $sp, $df, $sc]) {
            $technique = $this->technique($label);
            $submission = $this->service()->declare($one, $technique->id, 'https://youtu.be/a');

            $this->service()->confirm($submission, $tester, [
                'stability' => $st, 'speed' => $sp, 'difficulty' => $df, 'score' => $sc,
            ]);
        }

        // У второго два вида с низкими
        foreach (['B', 'C'] as $label) {
            $technique = $this->technique($label);
            $submission = $this->service()->declare($two, $technique->id, 'https://youtu.be/b');

            $this->service()->confirm($submission, $tester, [
                'stability' => 10, 'speed' => 10, 'difficulty' => 10, 'score' => 2,
            ]);
        }

        [$positionOne] = $this->service()->rankOf($one);
        [$positionTwo] = $this->service()->rankOf($two);

        $this->assertSame(2, $positionOne, 'Один вид ниже двух');
        $this->assertSame(1, $positionTwo, 'Больше видов — выше');
    }

    public function test_ranking_endpoint_returns_bridge_mode(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');
        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->getJson('/api/ranking?mode=bridge')
            ->assertOk()
            ->assertJsonPath('mode', 'bridge')
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.bridge_techniques_count', 1)
            ->assertJsonPath('data.0.bridge_aspects_total', 190);
    }

    public function test_profile_includes_bridge_data(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');
        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        Sanctum::actingAs($player);

        $this->getJson("/api/players/{$player->id}")
            ->assertOk()
            ->assertJsonPath('bridge.summary.confirmed_count', 1)
            ->assertJsonPath('bridge.summary.aspects_total', 190)
            ->assertJsonPath('bridge.techniques.0.technique.label', 'Telly Bridge');
    }

    public function test_player_can_withdraw_declared_technique(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($player);

        $this->deleteJson("/api/bridge/submissions/{$submission->id}")->assertOk();

        $this->assertSame(0, UserBridgeTechnique::query()->count());
    }

    public function test_another_player_cannot_withdraw(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $technique = $this->technique();

        $submission = $this->service()->declare($owner, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($other);

        $this->deleteJson("/api/bridge/submissions/{$submission->id}")->assertForbidden();
    }

    public function test_reviewers_are_notified_about_new_submission(): void
    {
        $player = User::factory()->create();
        $bridgeTester = User::factory()->create(['role' => 'bridge_tester']);
        $admin = User::factory()->create(['role' => 'admin']);
        $regularTester = User::factory()->create(['role' => 'tester']);

        $technique = $this->technique();

        Notification::fake();

        $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Notification::assertSentTo($bridgeTester, BridgeSubmissionNotification::class);
        Notification::assertSentTo($admin, BridgeSubmissionNotification::class);
        Notification::assertNotSentTo($regularTester, BridgeSubmissionNotification::class);
    }

    public function test_player_is_notified_about_review_result(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Notification::fake();

        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        Notification::assertSentTo(
            $player,
            \App\Domains\Bridge\Notifications\BridgeTechniqueReviewedNotification::class
        );
    }

    public function test_bridge_testers_are_excluded_from_rankings(): void
    {
        $bridgeTester = User::factory()->create(['role' => 'bridge_tester']);

        $this->assertTrue(
            $bridgeTester->isStaff(),
            'Проверяющий не должен соревноваться с теми, кого проверяет'
        );
    }
}
