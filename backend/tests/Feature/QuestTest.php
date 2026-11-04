<?php

namespace Tests\Feature;

use App\Domains\Players\Services\QuestService;
use App\Domains\Players\Support\PetQuests;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Доска заданий: набор дня, прогресс, награды, бонус дня, сбросы.
 */
class QuestTest extends TestCase
{
    use RefreshDatabase;

    private function player(): User
    {
        return User::factory()->create(['apex_coins' => 0]);
    }

    /** Довести первое задание дня до цели и вернуть его ключ. */
    private function finishFirstDaily(User $user): string
    {
        $key = array_key_first(PetQuests::today());
        $quest = PetQuests::today()[$key];

        QuestService::track($user, $quest['event'], $quest['target']);

        return $key;
    }

    public function test_board_has_four_daily_and_three_weekly_quests_same_for_everyone(): void
    {
        $a = $this->actingAs($this->player())->getJson('/api/quests')->assertOk();
        $b = $this->actingAs($this->player())->getJson('/api/quests')->assertOk();

        $a->assertJsonCount(PetQuests::DAILY_COUNT, 'daily')->assertJsonCount(PetQuests::WEEKLY_COUNT, 'weekly');
        $this->assertSame($a->json('daily.*.key'), $b->json('daily.*.key'));
        $this->assertSame($a->json('weekly.*.key'), $b->json('weekly.*.key'));
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/quests')->assertUnauthorized();
    }

    public function test_daily_set_changes_between_days(): void
    {
        $today = array_keys(PetQuests::today());

        $this->travel(1)->days();
        $tomorrow = array_keys(PetQuests::today());
        $this->travel(2)->days();
        $later = array_keys(PetQuests::today());

        // Три разных набора подряд почти наверняка не совпадают целиком
        $this->assertTrue($today !== $tomorrow || $tomorrow !== $later);
    }

    public function test_progress_counts_only_matching_events_and_is_capped(): void
    {
        $user = $this->player();
        $key = array_key_first(PetQuests::today());
        $quest = PetQuests::today()[$key];

        QuestService::track($user, 'something_else', 5);
        QuestService::track($user, $quest['event'], 1);
        QuestService::track($user, $quest['event'], 1000);

        $row = collect($this->actingAs($user)->getJson('/api/quests')->json('daily'))->firstWhere('key', $key);

        $this->assertSame($quest['target'], $row['progress']);
        $this->assertTrue($row['done']);
        $this->assertFalse($row['claimed']);
    }

    public function test_claim_pays_coins_and_tokens_once(): void
    {
        $user = $this->player();
        $key = $this->finishFirstDaily($user);
        $quest = PetQuests::today()[$key];

        $this->actingAs($user)->postJson("/api/quests/{$key}/claim")
            ->assertOk()
            ->assertJsonPath('reward.coins', $quest['coins'])
            ->assertJsonPath('reward.tokens', $quest['tokens']);

        $this->assertSame($quest['coins'], (int) $user->fresh()->apex_coins);
        $this->assertSame($quest['tokens'], (int) $user->fresh()->pet_tokens);

        $this->actingAs($user)->postJson("/api/quests/{$key}/claim")->assertStatus(422);
        $this->assertSame($quest['coins'], (int) $user->fresh()->apex_coins);
    }

    public function test_cannot_claim_unfinished_or_unknown_quest(): void
    {
        $user = $this->player();
        $key = array_key_first(PetQuests::today());

        $this->actingAs($user)->postJson("/api/quests/{$key}/claim")->assertStatus(422);
        $this->actingAs($user)->postJson('/api/quests/nope/claim')->assertStatus(422);
        $this->assertSame(0, (int) $user->fresh()->apex_coins);
    }

    public function test_daily_bonus_needs_all_claims_and_pays_once(): void
    {
        $user = $this->player();

        $this->actingAs($user)->postJson('/api/quests/bonus')->assertStatus(422);

        $coins = 0;
        $tokens = 0;

        foreach (PetQuests::today() as $key => $quest) {
            QuestService::track($user, $quest['event'], $quest['target']);
            $this->actingAs($user)->postJson("/api/quests/{$key}/claim")->assertOk();
            $coins += $quest['coins'];
            $tokens += $quest['tokens'];
        }

        // События совпадают у нескольких заданий: прогресс мог зачесться сверх — цель всё равно не выше target
        $this->actingAs($user)->postJson('/api/quests/bonus')
            ->assertOk()
            ->assertJsonPath('reward.coins', PetQuests::DAILY_BONUS['coins']);

        $this->assertSame($coins + PetQuests::DAILY_BONUS['coins'], (int) $user->fresh()->apex_coins);
        $this->assertSame($tokens + PetQuests::DAILY_BONUS['tokens'], (int) $user->fresh()->pet_tokens);

        $this->actingAs($user)->postJson('/api/quests/bonus')->assertStatus(422);
    }

    public function test_progress_resets_next_day(): void
    {
        $user = $this->player();
        $key = $this->finishFirstDaily($user);

        $this->travel(1)->days();

        $set = PetQuests::today();
        if (isset($set[$key])) {
            $row = collect($this->actingAs($user)->getJson('/api/quests')->json('daily'))->firstWhere('key', $key);
            $this->assertSame(0, $row['progress']);
        }

        // Старые строки остаются в журнале, новые периоды не пересекаются
        $this->assertSame(1, DB::table('pet_quests')->where('user_id', $user->id)->where('quest', $key)->count());
    }

    public function test_world_state_reports_claimable_quests_and_tokens(): void
    {
        $user = $this->player();
        $this->finishFirstDaily($user);

        $this->actingAs($user)->getJson('/api/world')
            ->assertOk()
            ->assertJsonPath('badges.quests', 1)
            ->assertJsonPath('tokens', 0);
    }

    public function test_rubles_are_never_a_quest_reward(): void
    {
        foreach ([...PetQuests::DAILY, ...PetQuests::WEEKLY] as $quest) {
            $this->assertArrayNotHasKey('rubles', $quest);
        }

        $this->assertArrayNotHasKey('rubles', PetQuests::DAILY_BONUS);
    }
}
