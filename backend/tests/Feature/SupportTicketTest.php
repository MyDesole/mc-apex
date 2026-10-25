<?php

namespace Tests\Feature;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_opens_ticket_staff_replies_and_player_is_notified(): void
    {
        $player = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderator']);

        $id = $this->actingAs($player)->postJson('/api/support/tickets', [
            'subject' => 'Не приходит письмо',
            'category' => 'account',
            'body' => 'Не могу подтвердить почту уже час',
        ])->assertCreated()->json('ticket.id');

        $this->actingAs($moderator)->getJson('/api/admin/counters')->assertJsonPath('support', 1);
        $this->actingAs($moderator)->getJson('/api/admin/support')->assertOk()->assertJsonPath('tickets.0.id', $id);

        $this->actingAs($moderator)->postJson("/api/admin/support/{$id}/messages", ['body' => 'Проверьте папку «Спам»'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'answered')
            ->assertJsonCount(2, 'ticket.messages');

        $note = $player->fresh()->unreadNotifications->first();
        $this->assertSame('support_reply', $note->data['type']);
        $this->assertSame($id, $note->data['ticket_id']);

        // Ответ игрока возвращает тикет в очередь, ведущему — уведомление
        $this->actingAs($player)->postJson("/api/support/tickets/{$id}/messages", ['body' => 'Нашёл, спасибо'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'open');
        $this->assertSame('support_player_reply', $moderator->fresh()->unreadNotifications->first()->data['type']);

        $this->actingAs($player)->postJson("/api/support/tickets/{$id}/close")->assertJsonPath('ticket.status', 'closed');
    }

    public function test_player_cannot_see_others_tickets_or_admin_queue(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $ticket = SupportTicket::create(['user_id' => $owner->id, 'subject' => 'Вопрос', 'status' => 'open', 'last_message_at' => now()]);

        $this->actingAs($stranger)->getJson("/api/support/tickets/{$ticket->id}")->assertNotFound();
        $this->actingAs($stranger)->getJson('/api/admin/support')->assertForbidden();
    }

    public function test_open_tickets_are_limited(): void
    {
        $player = User::factory()->create();

        foreach (range(1, 3) as $i) {
            SupportTicket::create(['user_id' => $player->id, 'subject' => "Вопрос {$i}", 'status' => 'open', 'last_message_at' => now()]);
        }

        $this->actingAs($player)->postJson('/api/support/tickets', ['subject' => 'Ещё один', 'body' => 'Текст вопроса'])
            ->assertStatus(422);
    }
}
