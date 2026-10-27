<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конструктор профиля: раскладка блоков и гостевая книга.
 */
class ProfileBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_are_saved_and_sanitized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => [
            ['id' => 'a1', 'type' => 'stats', 'width' => 'half', 'tone' => 'glass'],
            ['id' => 'a1', 'type' => 'text', 'width' => 'weird', 'settings' => ['title' => '<b>Привет</b>', 'body' => str_repeat('x', 3000), 'extra' => 1]],
            ['id' => 'v', 'type' => 'video', 'settings' => ['url' => 'https://evil.example/video']],
            ['id' => 'l', 'type' => 'links', 'settings' => ['items' => [['label' => 'Дискорд', 'url' => 'javascript:alert(1)'], ['label' => 'YT', 'url' => 'https://youtube.com/@me']]]],
            ['id' => 'x', 'type' => 'hacker'],
        ]])->assertOk();

        $blocks = $response->json('blocks');

        $this->assertCount(4, $blocks);
        $this->assertSame('half', $blocks[0]['width']);
        $this->assertSame('glass', $blocks[0]['tone']);
        // Повтор id переименован, ширина по умолчанию, теги убраны, длина обрезана
        $this->assertNotSame('a1', $blocks[1]['id']);
        $this->assertSame('full', $blocks[1]['width']);
        $this->assertSame('Привет', $blocks[1]['settings']['title']);
        $this->assertSame(2000, mb_strlen($blocks[1]['settings']['body']));
        $this->assertArrayNotHasKey('extra', $blocks[1]['settings']);
        // Видео только с YouTube, ссылки только http(s)
        $this->assertSame('', $blocks[2]['settings']['url']);
        $this->assertCount(1, $blocks[3]['settings']['items']);

        $this->assertSame($blocks, $user->fresh()->profile_blocks);

        // null — обратно к обычному профилю
        $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => null])->assertOk();
        $this->assertNull($user->fresh()->profile_blocks);
    }

    public function test_guestbook_needs_block_and_respects_delete_rights(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $stranger = User::factory()->create();

        // Нет блока — писать некуда
        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/guestbook", ['body' => 'Привет!'])->assertStatus(422);

        $owner->forceFill(['profile_blocks' => [['id' => 'g', 'type' => 'guestbook', 'width' => 'full', 'tone' => 'default', 'settings' => []]]])->save();

        $id = $this->actingAs($guest)->postJson("/api/players/{$owner->id}/guestbook", ['body' => 'Привет!'])
            ->assertCreated()
            ->json('entry.id');

        $this->getJson("/api/players/{$owner->id}/guestbook")->assertOk()->assertJsonPath('entries.0.body', 'Привет!');

        $this->actingAs($stranger)->deleteJson("/api/guestbook/{$id}")->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/guestbook/{$id}")->assertOk();
        $this->getJson("/api/players/{$owner->id}/guestbook")->assertJsonCount(0, 'entries');
    }
}
