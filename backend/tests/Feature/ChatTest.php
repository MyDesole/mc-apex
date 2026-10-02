<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Chat\Models\Message;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function pair(): array
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

    public function test_history_uses_cursor_pagination(): void
    {
        [$a, $b, $conversation] = $this->pair();

        // 60 сообщений: проверим, что страницы не пересекаются
        for ($i = 1; $i <= 60; $i++) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $i % 2 === 0 ? $a->id : $b->id,
                'body' => "Сообщение {$i}",
            ]);
        }

        $first = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}?limit=20")
            ->assertOk();

        $this->assertCount(20, $first->json('messages'));
        $this->assertTrue($first->json('has_more'));

        // Пришла самая свежая двадцатка, по возрастанию id
        $ids = collect($first->json('messages'))->pluck('id')->all();
        $this->assertSame($ids, collect($ids)->sort()->values()->all());
        $this->assertSame(60, end($ids));

        // Следующая страница — строго старее
        $oldestId = $first->json('oldest_id');

        $second = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}?limit=20&before_id={$oldestId}")
            ->assertOk();

        $secondIds = collect($second->json('messages'))->pluck('id')->all();

        $this->assertCount(20, $secondIds);
        $this->assertEmpty(array_intersect($ids, $secondIds), 'Страницы не должны пересекаться');
        $this->assertLessThan($oldestId, max($secondIds));
    }

    public function test_attachment_can_be_uploaded_and_sent(): void
    {
        Storage::fake('public');

        [$a, , $conversation] = $this->pair();

        $upload = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->image('screen.png', 800, 600)->size(400),
            ])
            ->assertCreated();

        $attachmentId = $upload->json('attachment.id');
        $this->assertTrue($upload->json('attachment.is_image'));

        $sent = $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Вот скриншот',
                'attachments' => [$attachmentId],
            ])
            ->assertCreated();

        $attachments = $sent->json('message.attachments');

        $this->assertCount(1, $attachments);
        $this->assertTrue($attachments[0]['is_image']);
        $this->assertNotEmpty($attachments[0]['url']);

        $this->assertDatabaseHas('message_attachments', [
            'id' => $attachmentId,
            'message_id' => $sent->json('message.id'),
        ]);
    }

    public function test_message_with_only_attachment_is_allowed(): void
    {
        Storage::fake('public');

        [$a, , $conversation] = $this->pair();

        $upload = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'attachments' => [$upload->json('attachment.id')],
            ])
            ->assertCreated();
    }

    public function test_empty_message_is_rejected(): void
    {
        [$a, , $conversation] = $this->pair();

        $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_outsider_cannot_read_attachments(): void
    {
        Storage::fake('public');

        [$a, , $conversation] = $this->pair();
        $stranger = User::factory()->create();

        $upload = $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->image('private.png'),
            ])
            ->assertCreated();

        $attachmentId = $upload->json('attachment.id');

        $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'attachments' => [$attachmentId],
            ])
            ->assertCreated();

        // Посторонний не должен скачать файл диалога
        $this->actingAs($stranger)
            ->get("/api/chat/attachments/{$attachmentId}/download")
            ->assertForbidden();

        // Участник — может
        $this->actingAs($a)
            ->get("/api/chat/attachments/{$attachmentId}/download")
            ->assertOk();
    }

    public function test_sender_can_edit_and_delete_own_message(): void
    {
        [$a, $b, $conversation] = $this->pair();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'body' => 'Исходный текст',
        ]);

        // Чужой не может
        $this->actingAs($b)
            ->putJson("/api/chat/messages/{$message->id}", ['body' => 'подмена'])
            ->assertForbidden();

        // Автор может
        $updated = $this->actingAs($a)
            ->putJson("/api/chat/messages/{$message->id}", ['body' => 'Исправленный текст'])
            ->assertOk();

        $this->assertSame('Исправленный текст', $updated->json('message.body'));
        $this->assertNotNull($updated->json('message.edited_at'));

        $this->actingAs($a)->deleteJson("/api/chat/messages/{$message->id}")->assertOk();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_mark_conversation_read_returns_fresh_counter(): void
    {
        [$a, $b, $conversation] = $this->pair();

        foreach (range(1, 5) as $i) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $b->id,
                'body' => "От {$b->username} #{$i}",
            ]);
        }

        $before = $this->actingAs($a)->getJson('/api/chat/unread-count')->assertOk();
        $this->assertSame(5, $before->json('unread_count'));

        $after = $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->assertSame(0, $after->json('unread_count'));

        $check = $this->actingAs($a)->getJson('/api/chat/unread-count')->assertOk();
        $this->assertSame(0, $check->json('unread_count'));
    }

    public function test_conversation_list_reports_unread_per_dialog(): void
    {
        [$a, $b, $conversation] = $this->pair();

        foreach (range(1, 3) as $i) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $b->id,
                'body' => "Привет #{$i}",
            ]);
        }

        $list = $this->actingAs($a)->getJson('/api/chat/conversations')->assertOk();

        $this->assertSame(3, $list->json('conversations.0.unread_count'));
    }

    public function test_attachment_upload_rejects_disallowed_type(): void
    {
        Storage::fake('public');

        [$a] = $this->pair();

        $this->actingAs($a)
            ->post('/api/chat/attachments', [
                'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            ])
            ->assertStatus(422);
    }
}
