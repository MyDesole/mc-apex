<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Message;
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
}
