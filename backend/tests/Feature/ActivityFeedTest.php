<?php

namespace Tests\Feature;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Activity\Events\ActivityRecorded;
use App\Domains\Activity\Models\Activity;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Лента «Сейчас на APEX».
 */
class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_tier_test_lands_in_feed_and_broadcasts(): void
    {
        Event::fake([ActivityRecorded::class]);

        $player = User::factory()->create(['username' => 'desole']);

        $test = TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'in_progress',
        ]);

        $this->assertSame(0, Activity::count());

        $test->update([
            'status' => 'completed',
            'result_tier' => 'B',
            'result_score' => 63.5,
        ]);

        $activity = Activity::sole();

        $this->assertSame(Activity::TIER_ASSIGNED, $activity->type);
        $this->assertSame($player->id, $activity->actor_id);
        $this->assertSame(['tier' => 'B', 'score' => 63.5, 'mode' => 'pvp'], $activity->payload);

        Event::assertDispatched(ActivityRecorded::class, function (ActivityRecorded $event) {
            return $event->activity['type'] === Activity::TIER_ASSIGNED
                && $event->activity['actor']['username'] === 'desole'
                && $event->broadcastOn()[0]->name === 'activity'
                && $event->broadcastAs() === 'activity.recorded';
        });
    }

    public function test_editing_completed_test_does_not_duplicate_event(): void
    {
        $player = User::factory()->create();

        $test = TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => 45,
        ]);

        $test->update(['notes' => 'поправили заметку']);

        $this->assertSame(1, Activity::count());
    }

    public function test_rolled_back_action_leaves_no_event(): void
    {
        Event::fake([ActivityRecorded::class]);

        $leader = User::factory()->create();

        try {
            DB::transaction(function () use ($leader) {
                Clan::create(['name' => 'Ghost', 'tag' => 'GST', 'leader_id' => $leader->id]);

                throw new \RuntimeException('откат');
            });
        } catch (\RuntimeException) {
            // ожидаемо
        }

        $this->assertSame(0, Activity::count());
        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_finished_clan_war_records_both_sides(): void
    {
        $a = Clan::create(['name' => 'Alpha', 'tag' => 'ALP', 'leader_id' => User::factory()->create()->id]);
        $b = Clan::create(['name' => 'Bravo', 'tag' => 'BRV', 'leader_id' => User::factory()->create()->id]);

        $war = ClanWar::create([
            'challenger_clan_id' => $a->id,
            'opponent_clan_id' => $b->id,
            'created_by' => $a->leader_id,
            'status' => 'accepted',
        ]);

        $war->update([
            'status' => 'completed',
            'challenger_score' => 3,
            'opponent_score' => 1,
            'winner_clan_id' => $a->id,
            'outcome' => 'win',
        ]);

        $event = Activity::where('type', Activity::CLAN_WAR_FINISHED)->sole();

        $this->assertSame('Alpha', $event->payload['challenger']['name']);
        $this->assertSame('Bravo', $event->payload['opponent']['name']);
        $this->assertSame(3, $event->payload['challenger_score']);
        $this->assertSame($a->id, $event->payload['winner_clan_id']);
    }

    public function test_granted_achievement_is_announced_once(): void
    {
        $user = User::factory()->create();

        Achievement::create([
            'code' => 'first_blood',
            'name' => 'Первая кровь',
            'description' => 'Пройти первый тир-тест',
            'icon' => '🗡',
            'color' => '#ef4444',
            'rarity' => 'rare',
            'points' => 10,
        ]);

        $this->assertTrue(AchievementService::grant($user, 'first_blood'));
        $this->assertFalse(AchievementService::grant($user, 'first_blood'));

        $event = Activity::where('type', Activity::ACHIEVEMENT_EARNED)->sole();

        $this->assertSame('Первая кровь', $event->payload['achievement']['name']);
    }

    public function test_minecraft_link_is_announced(): void
    {
        $user = User::factory()->create();

        $user->update(['minecraft_username' => 'Notch', 'minecraft_linked_at' => now()]);
        $user->update(['bio' => 'обновил описание']);

        $event = Activity::where('type', Activity::MINECRAFT_LINKED)->sole();

        $this->assertSame('Notch', $event->payload['nickname']);
    }

    public function test_endpoint_returns_newest_first_with_cursor(): void
    {
        $user = User::factory()->create(['username' => 'desole']);

        foreach (range(1, 5) as $i) {
            Activity::create(['type' => Activity::MINECRAFT_LINKED, 'actor_id' => $user->id, 'payload' => ['nickname' => "n{$i}"]]);
        }

        $first = $this->getJson('/api/activity?limit=2')->assertOk()->json('data');

        $this->assertSame(['n5', 'n4'], array_column(array_column($first, 'payload'), 'nickname'));
        $this->assertSame('desole', $first[0]['actor']['username']);

        $next = $this->getJson('/api/activity?limit=2&before=' . $first[1]['id'])->json('data');

        $this->assertSame(['n3', 'n2'], array_column(array_column($next, 'payload'), 'nickname'));
    }

    public function test_backfill_restores_history_in_chronological_order(): void
    {
        $player = User::factory()->create();
        $leader = User::factory()->create();

        TierTest::withoutEvents(fn () => TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'status' => 'completed',
            'result_tier' => 'A',
            'result_score' => 80,
            'completed_at' => now()->subDays(2),
        ]));

        Clan::withoutEvents(fn () => (new Clan)->forceFill([
            'name' => 'Old Clan',
            'tag' => 'OLD',
            'leader_id' => $leader->id,
            'created_at' => now()->subDays(5),
        ])->save());

        $this->artisan('apex:activity-backfill')->assertSuccessful();

        $this->assertSame(
            [Activity::CLAN_CREATED, Activity::TIER_ASSIGNED],
            Activity::orderBy('id')->pluck('type')->all()
        );

        // Повторный запуск без --fresh ничего не задваивает
        $this->artisan('apex:activity-backfill')->assertSuccessful();
        $this->assertSame(2, Activity::count());
    }
}
