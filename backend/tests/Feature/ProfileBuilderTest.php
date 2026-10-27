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

        $response = $this->actingAs($user)->putJson('/api/players/me/blocks', [
            'blocks' => [
                ['id' => 'a1', 'type' => 'stats', 'width' => 'half', 'tone' => 'glass', 'style' => ['color' => 'pink', 'height' => 'rocket', 'title' => '<i>Цифры</i>']],
                ['id' => 'a1', 'type' => 'text', 'width' => 'weird', 'settings' => ['body' => str_repeat('x', 3000), 'extra' => 1]],
                ['id' => 'v', 'type' => 'video', 'settings' => ['url' => 'https://evil.example/video']],
                ['id' => 'b', 'type' => 'bars', 'settings' => ['items' => [['label' => 'Бридж', 'value' => 250], ['label' => '', 'value' => 5]]]],
                ['id' => 'p', 'type' => 'players', 'settings' => ['items' => [['username' => 'Steve<script>']]]],
                ['id' => 's2', 'type' => 'stats'],
                ['id' => 'x', 'type' => 'hacker'],
            ],
            'page' => ['gap' => 'loose', 'header' => 'huge'],
        ])->assertOk();

        $blocks = $response->json('blocks');

        // Неизвестный тип и второй блок статистики отброшены
        $this->assertCount(5, $blocks);
        $this->assertSame('half', $blocks[0]['width']);
        $this->assertSame('glass', $blocks[0]['tone']);
        $this->assertSame('pink', $blocks[0]['style']['color']);
        $this->assertSame('auto', $blocks[0]['style']['height']);
        $this->assertSame('Цифры', $blocks[0]['style']['title']);
        // Повтор id переименован, ширина по умолчанию, длина обрезана
        $this->assertNotSame('a1', $blocks[1]['id']);
        $this->assertSame('full', $blocks[1]['width']);
        $this->assertSame(2000, mb_strlen($blocks[1]['settings']['body']));
        $this->assertArrayNotHasKey('extra', $blocks[1]['settings']);
        // Видео только с YouTube, полоски 0–100, ник только из допустимых символов
        $this->assertSame('', $blocks[2]['settings']['url']);
        $this->assertSame([['label' => 'Бридж', 'value' => 100]], $blocks[3]['settings']['items']);
        $this->assertSame('Stevescript', $blocks[4]['settings']['items'][0]['username']);

        $this->assertSame($blocks, $user->fresh()->profile_blocks);
        $this->assertSame('loose', $user->fresh()->profile_page['gap']);
        $this->assertSame('full', $user->fresh()->profile_page['header']);

        // null — обратно к обычному профилю
        $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => null])->assertOk();
        $this->assertNull($user->fresh()->profile_blocks);
        $this->assertNull($user->fresh()->profile_page);
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
