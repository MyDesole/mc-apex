<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Chat\Services\ChatService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatIncrementalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_history_returns_only_new_messages_without_marking_them_read(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = app(ChatService::class)->startDirect($reader, $sender->id);
        $first = Message::create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'First']);
        $second = Message::create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'Second']);
        $third = Message::create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'Third']);
        Sanctum::actingAs($reader);

        $this->getJson("/api/chat/conversations/{$conversation->id}?messages_only=1&mark_read=0&after_id={$first->id}&limit=1")
            ->assertOk()
            ->assertJsonMissingPath('conversation')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('newest_id', $second->id);
        $this->getJson("/api/chat/conversations/{$conversation->id}?messages_only=1&mark_read=0&after_id={$second->id}&limit=1")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $third->id)
            ->assertJsonPath('has_more', false);
        $this->assertDatabaseMissing('message_reads', ['user_id' => $reader->id]);
    }

    public function test_incremental_history_for_another_players_conversation_is_forbidden(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(ChatService::class)->startDirect($reader, $sender->id);
        Sanctum::actingAs($outsider);

        $this->getJson("/api/chat/conversations/{$conversation->id}?messages_only=1&mark_read=0")
            ->assertForbidden();
    }

    public function test_context_can_jump_over_a_thousand_messages_without_marking_history_read(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = app(ChatService::class)->startDirect($reader, $sender->id);
        $rows = [];
        for ($i = 0; $i < 1100; $i++) {
            $rows[] = ['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => "Message {$i}"];
        }
        Message::insert($rows);
        $target = Message::where('conversation_id', $conversation->id)->orderBy('id')->skip(50)->firstOrFail();
        Sanctum::actingAs($reader);
        $response = $this->getJson("/api/chat/conversations/{$conversation->id}?around_id={$target->id}&mark_read=0")
            ->assertOk()->assertJsonCount(41, 'messages')->assertJsonPath('messages.20.id', $target->id)
            ->assertJsonPath('has_more', true)->assertJsonPath('has_more_after', true);
        $this->assertDatabaseMissing('message_reads', ['user_id' => $reader->id]);
        $other = app(ChatService::class)->startDirect($reader, User::factory()->create()->id);
        $this->getJson("/api/chat/conversations/{$other->id}?around_id={$target->id}")->assertNotFound();
    }

    public function test_attachments_are_filtered_paginated_and_private_to_the_conversation(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = app(ChatService::class)->startDirect($reader, $sender->id);
        $message = Message::create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'Files']);
        for ($i = 0; $i < 42; $i++) {
            MessageAttachment::create(['message_id' => $message->id, 'user_id' => $sender->id, 'original_name' => "clip{$i}.mp4", 'path' => "chat/clip{$i}.mp4", 'mime' => 'video/mp4', 'size' => 100, 'is_image' => false, 'is_voice' => false]);
        }
        Sanctum::actingAs($reader);
        $page = $this->getJson("/api/chat/conversations/{$conversation->id}/attachments?type=video")
            ->assertOk()->assertJsonCount(40, 'attachments')->assertJsonPath('has_more', true)->assertJsonPath('attachments.0.is_video', true);
        $cursor = $page->json('next_cursor');
        $this->getJson("/api/chat/conversations/{$conversation->id}/attachments?type=video&before_id={$cursor}")
            ->assertOk()->assertJsonCount(2, 'attachments')->assertJsonPath('has_more', false);
        $this->getJson("/api/chat/conversations/{$conversation->id}/attachments?type=image")->assertOk()->assertJsonCount(0, 'attachments');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/chat/conversations/{$conversation->id}/attachments")->assertForbidden();
        $this->getJson("/api/chat/conversations/{$conversation->id}?around_id={$message->id}")->assertForbidden();
    }
}
