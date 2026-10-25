<?php

namespace Tests\Feature;

use App\Domains\Achievements\Services\AchievementRules;
use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Forum\Models\ForumCategory;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Новые ачивки: условия по группам и выдача по событиям.
 */
class AchievementRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    private function rules(): AchievementRules
    {
        return app(AchievementRules::class);
    }

    public function test_catalog_icons_are_pixel_icon_keys(): void
    {
        foreach ((new AchievementSeeder)->catalog() as $achievement) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $achievement['icon'], $achievement['code']);
        }
    }

    public function test_aspect_masters_and_all_rounder(): void
    {
        $user = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => 20,
            'rotka' => 18,
            'movement' => 15,
            'aim' => 16,
            'game_sense' => 17,
        ]);

        $codes = $this->rules()->eligible($user->fresh(), ['aspects']);

        $this->assertEqualsCanonicalizing(
            ['block_master', 'rod_master', 'aspect_perfect', 'all_rounder'],
            $codes,
        );
    }

    public function test_comeback_needs_a_better_result_in_the_same_mode(): void
    {
        $user = User::factory()->create();

        $make = fn (string $mode, float $score, int $daysAgo) => TierTest::withoutEvents(fn () => TierTest::create([
            'user_id' => $user->id,
            'mode' => $mode,
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => $score,
            'completed_at' => now()->subDays($daysAgo),
        ]));

        $make('pvp', 60, 10);
        $make('bedwars', 70, 5);

        $this->assertNotContains('tier_comeback', $this->rules()->eligible($user, ['tests']));

        $make('pvp', 64, 1);

        $this->assertContains('tier_comeback', $this->rules()->eligible($user, ['tests']));
    }

    public function test_pending_tests_do_not_count(): void
    {
        $user = User::factory()->create();

        TierTest::create(['user_id' => $user->id, 'mode' => 'pvp', 'status' => 'pending']);

        $this->assertNotContains('tier_test_first', $this->rules()->eligible($user, ['tests']));
    }

    public function test_top_three_between_top_ten_and_first(): void
    {
        User::factory()->create(['tier_score' => 90]);
        User::factory()->create(['tier_score' => 80]);

        $third = User::factory()->create(['tier_score' => 70]);

        $this->assertEqualsCanonicalizing(['top_10', 'top_3'], $this->rules()->eligible($third, ['top']));
    }

    public function test_group_check_grants_only_its_group(): void
    {
        $user = User::factory()->create(['tier' => 'B', 'tier_score' => 60]);

        AchievementService::check($user, ['minecraft']);

        $this->assertFalse($user->hasAchievement('tier_b'));
    }

    public function test_new_forum_topic_grants_forum_achievement(): void
    {
        $author = User::factory()->create();

        $category = ForumCategory::create(['name' => 'Общее', 'slug' => 'general']);

        ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Первая тема',
            'slug' => 'first',
            'body' => 'Привет',
        ]);

        $this->assertTrue($author->fresh()->hasAchievement('forum_topic_first'));
    }

    public function test_hundredth_message_grants_chat_achievement(): void
    {
        $author = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct', 'last_message_at' => now()]);

        Message::withoutEvents(function () use ($author, $conversation) {
            foreach (range(1, 99) as $i) {
                Message::create(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'body' => "#{$i}"]);
            }
        });

        $this->assertFalse($author->fresh()->hasAchievement('chat_100'));

        Message::create(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'body' => '#100']);

        $this->assertTrue($author->fresh()->hasAchievement('chat_100'));
    }

    public function test_linking_minecraft_grants_achievement(): void
    {
        $user = User::factory()->create();

        $user->update(['minecraft_username' => 'Notch', 'minecraft_linked_at' => now()]);

        $this->assertTrue($user->fresh()->hasAchievement('minecraft_linked'));
    }

    public function test_emoji_icons_are_converted_by_migration(): void
    {
        $migration = require database_path('migrations/2026_10_24_150000_pixel_icons_and_new_achievements.php');

        \App\Domains\Achievements\Models\Achievement::where('code', 'tier_b')->update(['icon' => '💎', 'name' => 'Своё имя']);
        \App\Domains\Achievements\Models\Achievement::create([
            'code' => 'custom_thanks', 'name' => 'Спасибо', 'description' => 'Ручная',
            'icon' => '🙏', 'color' => '#fff', 'rarity' => 'legendary', 'points' => 1,
        ]);

        $migration->up();

        $tierB = \App\Domains\Achievements\Models\Achievement::byCode('tier_b');

        $this->assertSame('diamond', $tierB->icon);
        $this->assertSame('Своё имя', $tierB->name, 'Правки админа не затираются');
        $this->assertSame('heart', \App\Domains\Achievements\Models\Achievement::byCode('custom_thanks')->icon);
    }
}
