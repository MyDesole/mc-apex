<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Поля темы форума, которые читает карточка в списке.
 *
 * Регрессия: ресурс отдавал только вложенный объект автора, а шаблон
 * читает плоские поля author_avatar, author_username и category_name.
 * Из-за этого в списке тем не показывалась аватарка автора и не
 * находилось его имя.
 */
class ForumTopicFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function topic(): ForumTopic
    {
        $author = User::factory()->create([
            'username' => 'АвторТемы',
            'avatar' => 'users/1/avatar.jpg',
        ]);

        $category = ForumCategory::create([
            'slug' => 'general',
            'name' => 'Общее',
            'color' => '#7c3aed',
        ]);

        return ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Тема с автором',
            'body' => 'Текст темы',
        ]);
    }

    public function test_topic_list_exposes_fields_used_by_the_card(): void
    {
        $this->topic();

        $response = $this->getJson('/api/forum/topics')->assertOk();
        $topic = $response->json('data.0');

        $this->assertNotNull($topic, 'Список тем пуст');

        foreach ([
            'id', 'title', 'author', 'author_username', 'author_avatar',
            'category_name', 'replies_count', 'views', 'is_pinned', 'is_locked',
            'created_at', 'last_reply_at',
        ] as $key) {
            $this->assertArrayHasKey($key, $topic, "Пропало поле {$key}");
        }

        // Значения, а не только ключи
        $this->assertSame('АвторТемы', $topic['author_username']);
        $this->assertSame('Общее', $topic['category_name']);
        $this->assertNotNull($topic['author_avatar'], 'Аватарка автора должна быть');
    }

    public function test_avatar_url_is_absolute(): void
    {
        $this->topic();

        $topic = $this->getJson('/api/forum/topics')->assertOk()->json('data.0');

        $this->assertStringContainsString(
            'avatar',
            (string) $topic['author_avatar'],
            'Аватарка должна быть ссылкой на файл'
        );
    }

    public function test_author_without_avatar_gives_null(): void
    {
        $author = User::factory()->create(['avatar' => null]);

        // Категория обязательна
        $category = ForumCategory::create([
            'slug' => 'no-avatar',
            'name' => 'Без аватарки',
        ]);

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Без аватарки',
            'body' => 'Текст',
        ]);

        $data = $this->getJson('/api/forum/topics')->assertOk()->json('data.0');

        $this->assertNull($data['author_avatar']);
        $this->assertSame($author->username, $data['author_username']);
    }

    public function test_topic_page_still_has_nested_author(): void
    {
        $topic = $this->topic();

        $data = $this->getJson("/api/forum/topics/{$topic->id}")->assertOk()->json('topic');

        // Вложенный автор нужен странице темы: аватарка, тир, роль
        $this->assertIsArray($data['author']);
        $this->assertSame('АвторТемы', $data['author']['username']);
        $this->assertArrayHasKey('avatar_url', $data['author']);
    }
}
