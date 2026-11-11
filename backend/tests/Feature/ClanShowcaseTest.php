<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Витрина публичной страницы клана: место, форма в войнах, активность и трофеи.
 */
class ClanShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function clan(string $tag, int $power): Clan
    {
        $leader = User::factory()->create();
        $clan = Clan::create(['name' => "Клан {$tag}", 'tag' => $tag, 'leader_id' => $leader->id]);
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader', 'joined_at' => now()]);
        // Силу задаём после вступления: вступление пересчитывает силу по рейтингу участников
        Clan::whereKey($clan->id)->update(['power' => $power]);

        return $clan;
    }

    public function test_guest_sees_showcase_with_rank_form_and_trophies(): void
    {
        $strong = $this->clan('AAA', 100);
        $weak = $this->clan('BBB', 10);

        ClanWar::create([
            'challenger_clan_id' => $strong->id, 'opponent_clan_id' => $weak->id, 'status' => 'completed',
            'created_by' => $strong->leader_id,
            'challenger_score' => 5, 'opponent_score' => 2, 'winner_clan_id' => $strong->id,
        ]);
        $strong->forceFill(['wins' => 1])->save();
        ClanCheckin::create(['clan_id' => $strong->id, 'user_id' => $strong->leader_id, 'day' => now()->toDateString()]);

        $response = $this->getJson("/api/clans/{$strong->id}")->assertOk();

        $response->assertJsonPath('showcase.rank.position', 1)
            ->assertJsonPath('showcase.form.0.result', 'win')
            ->assertJsonPath('showcase.form.0.us', 5)
            ->assertJsonPath('showcase.form.0.enemy.tag', 'BBB')
            ->assertJsonPath('showcase.streak.count', 1)
            ->assertJsonPath('showcase.activity.checkins', 1)
            ->assertJsonCount(7, 'showcase.activity.days');

        $trophies = collect($response->json('showcase.trophies'))->keyBy('key');
        $this->assertTrue($trophies['top1']['done']);
        $this->assertTrue($trophies['first_win']['done']);
        $this->assertFalse($trophies['wins10']['done']);

        $this->getJson("/api/clans/{$weak->id}")
            ->assertJsonPath('showcase.rank.position', 2)
            ->assertJsonPath('showcase.form.0.result', 'loss');
    }
}
