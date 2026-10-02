<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserInventory;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\ForumCategorySeeder;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Страж формы ответов API.
 *
 * Проверяет, что ответы содержат все поля, которые читают шаблоны.
 * Регрессия повторялась трижды: при переводе ответов на API Resources
 * из них выпадали поля, и интерфейс тихо показывал пустоту —
 * пропадало описание клана, лимит участников, аватарка автора темы,
 * подсветка клана на главной.
 *
 * Правило: добавил в шаблон новое поле — добавь его в список здесь.
 */
class ApiFieldsForTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
        $this->seed(ForumCategorySeeder::class);
        $this->seed(ShopItemSeeder::class);
    }

    /** Проверяет наличие полей в наборе данных. */
    private function assertFields(array $data, array $fields, string $where): void
    {
        $missing = array_values(array_filter(
            $fields,
            fn (string $field) => ! array_key_exists($field, $data)
        ));

        $this->assertSame(
            [],
            $missing,
            "{$where}: пропали поля — " . implode(', ', $missing)
        );
    }

    /**
     * Готовит игрока с кланом, темой форума, друзьями и покупкой.
     *
     * @return array{me: User, other: User, clan: Clan, topic: ForumTopic}
     */
    private function fixture(): array
    {
        $me = User::factory()->create(['username' => 'Страж', 'apex_coins' => 50000]);
        $other = User::factory()->create(['username' => 'Второй']);

        $clan = Clan::create([
            'name' => 'Клан стража',
            'tag' => 'GRD',
            'leader_id' => $me->id,
            'description' => 'Описание',
            'max_members' => 30,
        ]);

        \App\Models\ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $me->id,
            'role' => 'leader',
        ]);

        $category = ForumCategory::first();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $other->id,
            'title' => 'Тема',
            'body' => 'Текст',
        ]);

        ForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $me->id,
            'body' => 'Ответ',
        ]);

        $item = ShopItem::where('is_active', true)->first();

        UserInventory::create([
            'user_id' => $me->id,
            'shop_item_id' => $item->id,
            'quantity' => 1,
            'acquired_at' => now(),
        ]);

        \App\Models\Friendship::create([
            'user_id' => $me->id,
            'friend_id' => $other->id,
            'status' => 'accepted',
        ]);

        return ['me' => $me, 'other' => $other, 'clan' => $clan, 'topic' => $topic];
    }

    /* ==================== Кланы ==================== */

    public function test_clan_list_has_fields_used_by_cards(): void
    {
        $f = $this->fixture();

        $clan = $this->getJson('/api/clans')->assertOk()->json('data.0');

        $this->assertFields($clan, [
            'id', 'name', 'tag', 'description', 'max_members', 'members_count',
            'avatar', 'avatar_url', 'banner_color', 'power', 'wins', 'losses',
            'is_open', 'entry_fee', 'is_highlighted', 'my_clan_id',
            'highlight_until', 'highlight_color', 'highlight_effect', 'leader',
        ], 'GET /api/clans');

        $this->assertFields($clan['leader'], [
            'id', 'username', 'avatar_url', 'tier', 'tier_score', 'is_verified',
        ], 'GET /api/clans → leader');
    }

    public function test_home_clans_have_highlight_fields(): void
    {
        $this->fixture();

        $clan = $this->getJson('/api/home')->assertOk()->json('clans.0');

        $this->assertFields($clan, [
            'id', 'name', 'tag', 'avatar_url', 'banner_color', 'power',
            'wins', 'losses', 'members_count', 'leader',
            // Главная читает подсветку и её оформление
            'is_highlighted', 'highlight_color', 'highlight_effect', 'highlight_until',
        ], 'GET /api/home → clans');

        $this->assertFields($clan['leader'], [
            'id', 'username', 'avatar_url',
        ], 'GET /api/home → clans.leader');
    }

    public function test_top_clans_have_same_shape_as_home(): void
    {
        $this->fixture();

        $home = $this->getJson('/api/home')->assertOk()->json('clans.0');
        $top = $this->getJson('/api/top')->assertOk()->json('clans.0');

        $this->assertSame(
            array_keys($home),
            array_keys($top),
            'Топ кланов и главная должны отдавать одинаковые поля'
        );
    }

    public function test_home_players_have_card_fields(): void
    {
        $this->fixture();

        $player = $this->getJson('/api/home')->assertOk()->json('players.0');

        $this->assertFields($player, [
            'id', 'username', 'avatar_url', 'tier', 'tier_score',
            'clan_tag', 'clan_color',
        ], 'GET /api/home → players');
    }

    public function test_clan_page_has_fields(): void
    {
        $f = $this->fixture();

        $data = $this->getJson("/api/clans/{$f['clan']->id}")->assertOk();

        $this->assertFields($data->json('clan'), [
            'id', 'name', 'tag', 'description', 'banner_color', 'cover_url',
            'is_open', 'entry_fee', 'is_highlighted', 'highlight_until',
            'highlight_color', 'highlight_effect', 'socials', 'power',
            'wins', 'losses', 'max_members', 'avatar_url',
        ], 'GET /api/clans/{id} → clan');
    }

    /* ==================== Форум ==================== */

    public function test_forum_topic_list_has_fields_used_by_cards(): void
    {
        $this->fixture();

        $topic = $this->getJson('/api/forum/topics')->assertOk()->json('data.0');

        $this->assertFields($topic, [
            'id', 'title', 'body', 'author', 'author_username', 'author_avatar',
            'category', 'category_name', 'is_pinned', 'is_locked', 'views',
            'replies_count', 'likes_count', 'created_at', 'last_reply_at',
        ], 'GET /api/forum/topics');

        $this->assertFields($topic['author'], [
            'id', 'username', 'avatar_url', 'tier', 'role',
        ], 'GET /api/forum/topics → author');
    }

    public function test_forum_topic_page_has_fields(): void
    {
        $f = $this->fixture();

        $data = $this->getJson("/api/forum/topics/{$f['topic']->id}")->assertOk();

        $this->assertFields($data->json('topic'), [
            'id', 'title', 'body', 'author', 'category', 'attachments',
            'is_pinned', 'is_locked', 'views', 'replies_count', 'liked',
            'created_at', 'last_reply_at', 'can_edit', 'can_delete',
        ], 'GET /api/forum/topics/{id} → topic');

        foreach (['replies', 'replies_count', 'max_depth', 'can_reply'] as $key) {
            $this->assertArrayHasKey($key, $data->json(), "Страница темы: нет {$key}");
        }
    }

    public function test_forum_categories_have_fields(): void
    {
        $this->fixture();

        $category = $this->getJson('/api/forum')->assertOk()->json('categories.0');

        $this->assertFields($category, [
            'id', 'slug', 'name', 'description', 'icon', 'color',
            'post_policy', 'policy_label', 'can_post', 'topics_count',
            'replies_count', 'last_topic',
        ], 'GET /api/forum → categories');
    }

    /* ==================== Игроки, магазин, друзья ==================== */

    public function test_player_profile_has_fields(): void
    {
        $f = $this->fixture();

        $data = $this->getJson("/api/players/{$f['other']->id}")->assertOk();

        $this->assertFields($data->json('user'), [
            'id', 'username', 'avatar_url', 'tier', 'tier_score', 'bio',
            'status', 'quote', 'accent_color', 'banner_color',
            'avatar_frame', 'profile_effect', 'clan_member', 'aspects',
            'is_verified', 'role',
        ], 'GET /api/players/{id} → user');
    }

    public function test_shop_items_have_fields(): void
    {
        $f = $this->fixture();

        $item = $this->actingAs($f['me'])->getJson('/api/shop')->assertOk()->json('items.0');

        $this->assertFields($item, [
            'id', 'slug', 'name', 'description', 'type', 'rarity',
            'effect_value', 'price', 'icon', 'color', 'is_consumable',
            'is_repeatable', 'is_equippable', 'metadata', 'owned',
            'quantity', 'equipped', 'can_afford',
        ], 'GET /api/shop → items');
    }

    public function test_inventory_items_have_fields(): void
    {
        $f = $this->fixture();

        $item = $this->actingAs($f['me'])
            ->getJson('/api/shop/inventory')
            ->assertOk()
            ->json('items.0');

        $this->assertFields($item, [
            'id', 'shop_item_id', 'name', 'type', 'rarity', 'icon', 'color',
            'is_equippable', 'equipped', 'quantity', 'inventory_id',
        ], 'GET /api/shop/inventory → items');
    }

    public function test_friends_have_fields(): void
    {
        $f = $this->fixture();

        $data = $this->actingAs($f['me'])->getJson('/api/friends')->assertOk();

        $this->assertFields($data->json('friends.0'), [
            'id', 'username', 'avatar_url', 'tier', 'tier_score', 'is_verified',
        ], 'GET /api/friends → friends');

        foreach (['friends', 'incoming_requests', 'outgoing_requests'] as $key) {
            $this->assertArrayHasKey($key, $data->json(), "Друзья: нет {$key}");
        }
    }

    public function test_ranking_players_have_fields(): void
    {
        $f = $this->fixture();

        // Дадим игроку аспекты, чтобы он попал в рейтинг
        \App\Models\PlayerAspectPvp::create([
            'user_id' => $f['other']->id,
            'block_placing' => 10, 'rotka' => 0, 'movement' => 0, 'aim' => 0, 'game_sense' => 0,
        ]);

        $player = $this->getJson('/api/ranking')->assertOk()->json('data.0');

        $this->assertFields($player, [
            'position', 'id', 'username', 'avatar_url', 'tier', 'tier_score',
            'rating_score', 'clan_tag', 'clan_color',
        ], 'GET /api/ranking → data');
    }
}
