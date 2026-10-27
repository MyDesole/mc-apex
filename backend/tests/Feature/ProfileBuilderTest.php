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
            'page' => ['gap' => 'loose', 'header' => 'huge', 'bg' => 'aurora', 'bg_from' => '#FF00AA', 'accent' => 'red', 'glass' => 5, 'blur' => 99, 'hdr_layout' => 'banner', 'hdr_name' => 'rainbow', 'fx' => 'snow', 'accent' => '#ff0000',
                'hdr_items' => [['id' => 'stats', 'hidden' => 1], ['id' => 'hack'], ['id' => 'name', 'size' => 'xl', 'font' => 'comic'], ['id' => 'stats']]],
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
        // Студия: цвета только #rrggbb, числа зажаты в рамки
        $page = $user->fresh()->profile_page;
        $this->assertSame('aurora', $page['bg']);
        $this->assertSame('#ff00aa', $page['bg_from']);
        // Платного из магазина студия не принимает: акцент, эффекты ника и профиля
        $this->assertArrayNotHasKey('accent', $page);
        $this->assertArrayNotHasKey('hdr_name', $page);
        $this->assertArrayNotHasKey('fx', $page);
        $this->assertSame(20, $page['glass']);
        $this->assertSame(24, $page['blur']);
        $this->assertSame('banner', $page['hdr_layout']);
        // Части шапки: свой порядок, неизвестные выкинуты, недостающие дописаны в конец
        $this->assertSame(['stats', 'name', 'role', 'progress'], array_column($page['hdr_items'], 'id'));
        $this->assertSame('xl', $page['hdr_items'][1]['size']);
        $this->assertSame('default', $page['hdr_items'][1]['font']);
        $this->assertTrue($page['hdr_items'][0]['hidden']);

        // Пустой список — профиль без блоков, а не обычный профиль
        $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => []])
            ->assertOk()
            ->assertJsonPath('blocks', []);
        $this->assertSame([], $user->fresh()->profile_blocks);

        // null — обратно к обычному профилю
        $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => null])->assertOk();
        $this->assertNull($user->fresh()->profile_blocks);
        $this->assertNull($user->fresh()->profile_page);
    }

    public function test_teammates_are_stored_by_id_and_served_fresh(): void
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['username' => 'Mate_1']);

        $blocks = $this->actingAs($owner)->putJson('/api/players/me/blocks', ['blocks' => [
            ['id' => 'p', 'type' => 'players', 'settings' => ['items' => [
                ['user_id' => $mate->id, 'username' => 'Mate_1', 'role' => 'coach', 'note' => 'Учит клатчить'],
                ['user_id' => $mate->id, 'username' => 'Mate_1'],
                ['user_id' => 0, 'username' => 'Old', 'role' => 'boss'],
            ]]],
        ]])->assertOk()->json('blocks');

        // Повтор того же игрока выкинут, неизвестная роль — дуо
        $items = $blocks[0]['settings']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('coach', $items[0]['role']);
        $this->assertSame('duo', $items[1]['role']);

        $this->getJson("/api/players/teammates?ids={$mate->id},999999,abc")
            ->assertOk()
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.username', 'Mate_1');
    }

    public function test_poll_goals_counter_and_voting(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();

        $blocks = $this->actingAs($owner)->putJson('/api/players/me/blocks', ['blocks' => [
            ['id' => 'poll1', 'type' => 'poll', 'settings' => ['question' => 'Лучший режим?', 'options' => ['Бридж', 'BedWars', 'PvP', 'Скайварс', 'Лишний']]],
            ['id' => 'g', 'type' => 'goals', 'settings' => ['items' => [['text' => 'Тир A', 'done' => true], ['text' => '']]]],
            ['id' => 'c', 'type' => 'counter', 'settings' => ['value' => '1200<script>', 'label' => 'побед'], 'style' => ['border' => 'rainbow', 'icon' => '🔥🔥🔥🔥🔥']],
        ]])->assertOk()->json('blocks');

        $this->assertCount(4, $blocks[0]['settings']['options']);
        $this->assertCount(1, $blocks[1]['settings']['items']);
        $this->assertSame('1200', $blocks[2]['settings']['value']);
        $this->assertSame('rainbow', $blocks[2]['style']['border']);

        $base = "/api/players/{$owner->id}/polls/poll1";

        $this->actingAs($guest)->postJson($base, ['option' => 1])
            ->assertOk()
            ->assertJsonPath('votes', [0, 1, 0, 0])
            ->assertJsonPath('mine', 1);

        // Переголосовать — голос переезжает, а не добавляется
        $this->actingAs($guest)->postJson($base, ['option' => 0])->assertJsonPath('total', 1);

        $this->getJson($base)->assertOk()->assertJsonPath('votes.0', 1)->assertJsonPath('mine', 0);
        $this->getJson("/api/players/{$owner->id}/polls/nope")->assertNotFound();
    }

    public function test_new_blocks_and_block_style_extras_are_sanitized(): void
    {
        $user = User::factory()->create();

        $blocks = $this->actingAs($user)->putJson('/api/players/me/blocks', ['blocks' => [
            ['id' => 'st', 'type' => 'setup', 'settings' => ['items' => [['kind' => 'mouse', 'value' => 'G Pro'], ['kind' => 'laser', 'value' => '800 DPI'], ['kind' => 'pc', 'value' => '']]]],
            ['id' => 'ln', 'type' => 'links', 'settings' => ['style' => 'weird', 'items' => [
                ['label' => 'ДС', 'url' => 'https://discord.gg/x', 'icon' => 'discord', 'color' => '#5865F2'],
                ['label' => 'Плохая', 'url' => 'javascript:alert(1)', 'icon' => 'bomb'],
            ]]],
            ['id' => 'mu', 'type' => 'music', 'settings' => ['url' => 'https://evil.example/track', 'title' => 'Трек']],
            ['id' => 'mu2', 'type' => 'music', 'settings' => ['url' => 'https://open.spotify.com/track/abc']],
            ['id' => 'pr', 'type' => 'progress', 'settings' => ['title' => 'До тира A', 'current' => -5, 'target' => 0]],
            ['id' => 'mq', 'type' => 'marquee', 'settings' => ['text' => 'Привет', 'speed' => 'ludicrous']],
            ['id' => 'sc', 'type' => 'schedule', 'settings' => ['items' => [['day' => 'fri', 'time' => '20:00', 'text' => 'Стрим'], ['day' => 'xyz', 'text' => 'Бридж']]]],
            ['id' => 'tx', 'type' => 'text', 'style' => ['grad_from' => '#FF0000', 'grad_to' => 'nope', 'grad_angle' => 999, 'bw' => 40, 'anim' => 'float', 'reveal' => 'teleport', 'pad' => 'xl']],
        ]])->assertOk()->json('blocks');

        // Сетап: неизвестный вид — «другое», пустая строка выкинута
        $this->assertSame([['kind' => 'mouse', 'value' => 'G Pro'], ['kind' => 'other', 'value' => '800 DPI']], $blocks[0]['settings']['items']);
        // Ссылки: только http(s), стиль и иконка из списка, цвет в нижнем регистре
        $this->assertSame('solid', $blocks[1]['settings']['style']);
        $this->assertCount(1, $blocks[1]['settings']['items']);
        $this->assertSame('#5865f2', $blocks[1]['settings']['items'][0]['color']);
        // Музыка — только с известных сервисов
        $this->assertSame('', $blocks[2]['settings']['url']);
        $this->assertSame('https://open.spotify.com/track/abc', $blocks[3]['settings']['url']);
        $this->assertSame(0, $blocks[4]['settings']['current']);
        $this->assertSame(1, $blocks[4]['settings']['target']);
        $this->assertSame('normal', $blocks[5]['settings']['speed']);
        $this->assertSame('mon', $blocks[6]['settings']['items'][1]['day']);
        // Оформление: цвета #rrggbb, числа в рамках, перечисления из списка
        $style = $blocks[7]['style'];
        $this->assertSame('#ff0000', $style['grad_from']);
        $this->assertSame('', $style['grad_to']);
        $this->assertSame(360, $style['grad_angle']);
        $this->assertSame(6, $style['bw']);
        $this->assertSame('float', $style['anim']);
        $this->assertSame('none', $style['reveal']);
        $this->assertSame('xl', $style['pad']);
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
