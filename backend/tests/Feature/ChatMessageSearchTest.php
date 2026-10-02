<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Поиск по сообщениям внутри диалога.
 *
 * Искать можно только в своём диалоге: доступ проверяется политикой.
 */
class ChatMessageSearchTest extends TestCase
{
    use RefreshDatabase;

    /** Личный диалог с набором сообщений. */
    private function conversation(): array
    {
        $a = User::factory()->create(['username' => 'alpha']);
        $b = User::factory()->create(['username' => 'beta']);

        $conversation = Conversation::create([
            'type' => 'direct',
            'created_by' => $a->id,
        ]);

        $conversation->users()->attach([$a->id, $b->id]);

        return [$a, $b, $conversation];
    }

    /** Добавляет сообщение в диалог. */
    private function say(Conversation $conversation, User $author, ?string $body): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    public function test_finds_message_by_text(): void
    {
        [$a, $b, $conversation] = $this->conversation();

        $this->say($conversation, $a, 'Привет, как дела');
        $this->say($conversation, $b, 'Всё хорошо, играю в bedwars');
        $this->say($conversation, $a, 'Пойдём в клан');

        $response = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=bedwars")
            ->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('Всё хорошо, играю в bedwars', $response->json('messages.0.body'));
        $this->assertSame('beta', $response->json('messages.0.user.username'));
    }

    public function test_search_is_case_insensitive(): void
    {
        [$a, , $conversation] = $this->conversation();

        $this->say($conversation, $a, 'Играю в BEDWARS каждый день');

        $response = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=bedwars")
            ->assertOk();

        $this->assertSame(1, $response->json('total'));
    }

    public function test_short_query_returns_nothing(): void
    {
        [$a, , $conversation] = $this->conversation();

        $this->say($conversation, $a, 'Привет');

        $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=п")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_snippet_shows_text_around_match(): void
    {
        [$a, , $conversation] = $this->conversation();

        $long = str_repeat('Слово ', 40) . 'bedwars' . str_repeat(' ещё', 40);

        $this->say($conversation, $a, $long);

        $response = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=bedwars")
            ->assertOk();

        $snippet = $response->json('messages.0.snippet');

        $this->assertStringContainsString('bedwars', $snippet, 'совпадение попало во фрагмент');
        $this->assertLessThan(mb_strlen($long), mb_strlen($snippet), 'фрагмент короче сообщения');
    }

    public function test_only_searches_own_conversation(): void
    {
        [$a, , $conversation] = $this->conversation();

        $this->say($conversation, $a, 'секретное слово');

        /* Посторонний игрок */
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=секретное")
            ->assertForbidden();
    }

    public function test_does_not_find_messages_from_other_conversations(): void
    {
        [$a, $b, $conversation] = $this->conversation();

        /* Второй диалог с тем же игроком */
        $other = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        $other->users()->attach([$a->id, $b->id]);

        $this->say($conversation, $a, 'ищу это слово');
        $this->say($other, $a, 'ищу это слово тоже');

        $response = $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=ищу")
            ->assertOk();

        $this->assertSame(1, $response->json('total'), 'только своё сообщение из этого диалога');
    }

    public function test_skips_messages_without_text(): void
    {
        [$a, , $conversation] = $this->conversation();

        $this->say($conversation, $a, null);
        $this->say($conversation, $a, 'есть текст');

        $this->actingAs($a)
            ->getJson("/api/chat/conversations/{$conversation->id}/search?q=текст")
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_guest_cannot_search(): void
    {
        [, , $conversation] = $this->conversation();

        $this->getJson("/api/chat/conversations/{$conversation->id}/search?q=слово")
            ->assertUnauthorized();
    }
}
