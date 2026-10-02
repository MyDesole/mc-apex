<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Пакетная загрузка вложений в чат.
 *
 * Раньше эндпоинт принимал ровно один файл за запрос, поэтому пачка
 * из нескольких файлов означала несколько последовательных обращений.
 * Здесь фиксируется поддержка обоих вариантов: одиночный файл (обратная
 * совместимость) и массив файлов.
 */
class ChatMultiUploadTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'last_message_at' => now(),
        ]);

        foreach ([$a, $b] as $user) {
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ]);
        }

        return [$a, $b, $conversation];
    }

    public function test_single_file_still_works(): void
    {
        Storage::fake('public');

        [$a] = $this->conversation();

        $response = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->image('one.png', 100, 100),
            ])
            ->assertCreated();

        // Обратная совместимость: одиночный ответ с ключом attachment
        $this->assertNotNull($response->json('attachment.id'));
        $this->assertTrue($response->json('attachment.is_image'));
    }

    public function test_multiple_files_uploaded_in_one_request(): void
    {
        Storage::fake('public');

        [$a] = $this->conversation();

        $response = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'files' => [
                    UploadedFile::fake()->image('first.png', 100, 100),
                    UploadedFile::fake()->image('second.png', 200, 200),
                    UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertCreated();

        $attachments = $response->json('attachments');

        $this->assertCount(3, $attachments);

        $names = array_column($attachments, 'name');
        $this->assertContains('first.png', $names);
        $this->assertContains('second.png', $names);
        $this->assertContains('doc.pdf', $names);

        // Картинки помечены как изображения, pdf — нет
        $byName = collect($attachments)->keyBy('name');
        $this->assertTrue($byName['first.png']['is_image']);
        $this->assertFalse($byName['doc.pdf']['is_image']);

        // Все файлы реально сохранены
        foreach ($attachments as $attachment) {
            $this->assertNotEmpty($attachment['url']);
        }

        $this->assertDatabaseCount('message_attachments', 3);
    }

    public function test_all_uploaded_files_can_be_attached_to_one_message(): void
    {
        Storage::fake('public');

        [$a, , $conversation] = $this->conversation();

        $upload = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'files' => [
                    UploadedFile::fake()->image('a.png'),
                    UploadedFile::fake()->image('b.png'),
                ],
            ])
            ->assertCreated();

        $ids = array_column($upload->json('attachments'), 'id');

        $message = $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Два файла',
                'attachments' => $ids,
            ])
            ->assertCreated();

        $this->assertCount(2, $message->json('message.attachments'));
    }

    public function test_too_many_files_in_one_request_are_rejected(): void
    {
        Storage::fake('public');

        [$a] = $this->conversation();

        // Больше 10 файлов за раз
        $files = [];

        foreach (range(1, 11) as $i) {
            $files[] = UploadedFile::fake()->image("f{$i}.png");
        }

        $this->actingAs($a)
            ->post('/api/chat/attachments', ['files' => $files])
            ->assertStatus(422);
    }

    public function test_disallowed_file_in_batch_rejects_whole_request(): void
    {
        Storage::fake('public');

        [$a] = $this->conversation();

        $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'files' => [
                    UploadedFile::fake()->image('good.png'),
                    UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
                ],
            ])
            ->assertStatus(422);

        // Ничего не сохранилось
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_mixed_single_and_array_payload_uses_array(): void
    {
        Storage::fake('public');

        [$a] = $this->conversation();

        $response = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->image('single.png'),
                'files' => [
                    UploadedFile::fake()->image('from-array.png'),
                ],
            ])
            ->assertCreated();

        // Приоритет у массива: в ответе оба ключа, но загружен файл из files
        $this->assertCount(1, $response->json('attachments'));
        $this->assertSame('from-array.png', $response->json('attachments.0.name'));
    }

    public function test_guest_cannot_upload_batch(): void
    {
        Storage::fake('public');

        $this->postJson('/api/chat/attachments', [])->assertUnauthorized();
    }
}
