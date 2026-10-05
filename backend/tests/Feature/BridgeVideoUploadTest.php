<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\BridgeVideoUpload;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Чанковая загрузка видео бридж-заявок.
 *
 * Ролик грузится частями и лежит в закрытом хранилище. После проверки
 * тестером видео удаляется.
 */
class BridgeVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function technique(): BridgeTechnique
    {
        return BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);
    }

    /**
     * Прогоняет полный цикл чанковой загрузки.
     *
     * Размер объявляем ровно тот, сколько реально шлём: сервер сверяет
     * собранный файл с заявленным размером.
     */
    private function uploadVideo(User $user, int $size = 1024): string
    {
        Sanctum::actingAs($user);

        $init = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => $size,
            'mime' => 'video/mp4',
        ])->assertCreated();

        $uuid = $init->json('uuid');
        $chunkSize = (int) $init->json('chunk_size');
        $total = (int) ceil($size / $chunkSize);

        for ($index = 0; $index < $total; $index++) {
            $bytes = min($chunkSize, $size - $index * $chunkSize);

            $this->post(
                "/api/bridge/uploads/{$uuid}/chunks",
                [
                    'index' => $index,
                    'chunk' => UploadedFile::fake()->createWithContent(
                    "part{$index}.mp4",
                    str_repeat('A', $bytes),
                ),
                ],
                ['Accept' => 'application/json'],
            )->assertOk();
        }

        $this->postJson("/api/bridge/uploads/{$uuid}/complete")->assertOk();

        return $uuid;
    }

    public function test_init_creates_upload_session(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => 10_000_000,
            'mime' => 'video/mp4',
        ])
            ->assertCreated()
            ->assertJsonStructure(['uuid', 'chunk_size', 'received']);

        $this->assertSame(1, BridgeVideoUpload::query()->count());
    }

    public function test_too_large_video_is_rejected(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/uploads', [
            'file_name' => 'huge.mp4',
            'size' => BridgeVideoUpload::MAX_SIZE + 1,
            'mime' => 'video/mp4',
        ])->assertStatus(422)->assertJsonValidationErrors(['size']);
    }

    public function test_unsupported_format_is_rejected(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/uploads', [
            'file_name' => 'clip.avi.txt',
            'size' => 1024,
            'mime' => 'text/plain',
        ])->assertStatus(422);
    }

    public function test_two_chunks_are_assembled_into_one_file(): void
    {
        $player = User::factory()->create();

        // Больше одной части, но ровно по границе чанка
        $size = BridgeVideoUpload::CHUNK_SIZE + 1024;

        $uploadId = $this->uploadVideo($player, $size);

        $upload = BridgeVideoUpload::query()->where('uuid', $uploadId)->first();

        $this->assertTrue($upload->isCompleted());
        $this->assertSame(2, $upload->total_chunks);
        $this->assertSame($size, Storage::disk('local')->size($upload->path));

        // Временные части подчищены
        $this->assertFalse(Storage::disk('local')->exists("bridge-chunks/{$uploadId}"));
    }

    public function test_repeated_chunk_does_not_break_upload(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $init = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => 1024,
            'mime' => 'video/mp4',
        ])->assertCreated();

        $uuid = $init->json('uuid');

        // Одна и та же часть дважды: после обрыва связи фронтенд повторяет
        for ($i = 0; $i < 2; $i++) {
            $this->post(
                "/api/bridge/uploads/{$uuid}/chunks",
                [
                    'index' => 0,
                    'chunk' => UploadedFile::fake()->createWithContent('part.mp4', str_repeat('A', 1024)),
                ],
                ['Accept' => 'application/json'],
            )->assertOk();
        }

        $this->assertSame(
            [0],
            BridgeVideoUpload::query()->where('uuid', $uuid)->first()->received_chunks,
        );
    }

    public function test_complete_fails_while_chunks_are_missing(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $init = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => BridgeVideoUpload::CHUNK_SIZE * 3,
            'mime' => 'video/mp4',
        ])->assertCreated();

        $uuid = $init->json('uuid');

        $this->post(
            "/api/bridge/uploads/{$uuid}/chunks",
            [
                'index' => 0,
                'chunk' => UploadedFile::fake()->createWithContent('part.mp4', str_repeat('A', 4096)),
            ],
            ['Accept' => 'application/json'],
        )->assertOk();

        $this->postJson("/api/bridge/uploads/{$uuid}/complete")->assertStatus(422);
    }

    public function test_wrong_chunk_size_does_not_produce_broken_video(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        // Заявляем 10 КБ, а присылаем одну часть на 1 КБ
        $init = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'bridge.mp4',
            'size' => 10240,
            'mime' => 'video/mp4',
        ])->assertCreated();

        $uuid = $init->json('uuid');

        $this->post(
            "/api/bridge/uploads/{$uuid}/chunks",
            [
                'index' => 0,
                'chunk' => UploadedFile::fake()->createWithContent('part.mp4', str_repeat('A', 1024)),
            ],
            ['Accept' => 'application/json'],
        )->assertOk();

        // Сборка должна отказать: размер не сходится
        $this->postJson("/api/bridge/uploads/{$uuid}/complete")->assertStatus(422);

        $upload = BridgeVideoUpload::query()->where('uuid', $uuid)->first();

        $this->assertFalse($upload->isCompleted());
        $this->assertNull($upload->path);
    }

    public function test_another_player_cannot_push_chunks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $foreign = $this->postJson('/api/bridge/uploads', [
            'file_name' => 'mine.mp4',
            'size' => 1024,
            'mime' => 'video/mp4',
        ])->json('uuid');

        Sanctum::actingAs($owner);

        $this->post(
            "/api/bridge/uploads/{$foreign}/chunks",
            [
                'index' => 0,
                'chunk' => UploadedFile::fake()->createWithContent('part.mp4', str_repeat('A', 1024)),
            ],
            ['Accept' => 'application/json'],
        )->assertForbidden();
    }

    public function test_declare_uses_uploaded_video(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();

        $this->assertNotNull($submission->video_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->video_path));
    }

    public function test_declare_requires_upload(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['upload_id']);

        $this->assertSame(0, UserBridgeTechnique::query()->count());
    }

    public function test_declare_rejects_someone_elses_upload(): void
    {
        $player = User::factory()->create();
        $other = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($other);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertStatus(422);
    }

    public function test_video_is_deleted_after_confirmation(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();
        $path = $submission->video_path;

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => true,
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ])->assertOk();

        // Тест проведён — ролик больше не храним
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNull($submission->fresh()->video_path);
    }

    public function test_video_is_deleted_after_rejection(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();
        $path = $submission->video_path;

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/submissions/{$submission->id}", [
            'confirm' => false,
            'notes' => 'Не видно кпс мод',
        ])->assertOk();

        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_video_is_deleted_when_player_withdraws(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();
        $path = $submission->video_path;

        $this->deleteJson("/api/bridge/submissions/{$submission->id}")->assertOk();

        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_signed_url_serves_the_video(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();

        $url = URL::temporarySignedRoute(
            'bridge.video',
            now()->addHour(),
            ['submission' => $submission->id],
        );

        $this->get($url)->assertOk();
    }

    public function test_video_is_not_served_without_signature(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $submission = UserBridgeTechnique::query()->first();

        // Прямой адрес без подписи не работает
        $this->getJson("/api/bridge/videos/{$submission->id}")->assertForbidden();
    }

    public function test_submission_resource_gives_signed_url(): void
    {
        $player = User::factory()->create();
        $technique = $this->technique();

        $uploadId = $this->uploadVideo($player);

        Sanctum::actingAs($player);

        $this->postJson('/api/bridge/techniques', [
            'technique_id' => $technique->id,
            'upload_id' => $uploadId,
        ])->assertCreated();

        $response = $this->getJson('/api/bridge/techniques')->assertOk();

        $url = $response->json('data.0.submission.video_url');

        $this->assertStringContainsString('signature=', (string) $url);
        $this->assertTrue($response->json('data.0.submission.has_video'));
    }

    public function test_stale_uploads_are_pruned(): void
    {
        $player = User::factory()->create();

        $upload = BridgeVideoUpload::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $player->id,
            'original_name' => 'old.mp4',
            'mime' => 'video/mp4',
            'size' => 1024,
            'total_chunks' => 1,
            'received_chunks' => [],
            'status' => BridgeVideoUpload::STATUS_PENDING,
        ]);

        $upload->forceFill(['created_at' => now()->subDays(2)])->save();

        $removed = app(\App\Domains\Bridge\Services\BridgeVideoService::class)->pruneStale();

        $this->assertSame(1, $removed);
        $this->assertSame(0, BridgeVideoUpload::query()->count());
    }
}
