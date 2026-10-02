<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Список кланов и признак «это мой клан».
 *
 * Фронтенд по этому признаку решает, куда вести клик по клану:
 * в общий раздел клана или во вкладку «Мой клан».
 */
class ClanListOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function clan(string $name, User $leader): Clan
    {
        $clan = Clan::create([
            'name' => $name,
            'tag' => mb_substr($name, 0, 3),
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return $clan;
    }

    public function test_list_reports_which_clan_is_mine(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->clan('Мой клан', $me);
        $this->clan('Чужой клан', $other);

        $response = $this->actingAs($me)->getJson('/api/clans')->assertOk();

        $clans = collect($response->json('data'))->keyBy('id');

        $this->assertSame($mine->id, $clans[$mine->id]['my_clan_id']);
        $this->assertNull($clans->firstWhere('name', 'Чужой клан')['my_clan_id']);
    }

    public function test_guest_has_no_own_clan(): void
    {
        $leader = User::factory()->create();
        $this->clan('Любой клан', $leader);

        $response = $this->getJson('/api/clans')->assertOk();

        $this->assertNull($response->json('data.0.my_clan_id'));
    }

    public function test_member_without_clan_sees_no_ownership(): void
    {
        $leader = User::factory()->create();
        $this->clan('Чужой клан', $leader);

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->getJson('/api/clans')->assertOk();

        $this->assertNull($response->json('data.0.my_clan_id'));
    }

    /**
     * Все поля, которые читает карточка клана в списке.
     *
     * Регрессия: при переводе списка на ClanCardResource из ресурса
     * выпали description и max_members — в интерфейсе описание
     * пропадало, а лимит участников показывался как 0.
     */
    public function test_list_exposes_every_field_used_by_the_card(): void
    {
        $me = User::factory()->create();

        $clan = $this->clan('Клан формы', $me);
        $clan->update(['description' => 'Описание клана', 'max_members' => 30]);

        $response = $this->actingAs($me)->getJson('/api/clans')->assertOk();
        $data = $response->json('data.0');

        foreach ([
            'id', 'name', 'tag', 'description', 'max_members', 'members_count',
            'power', 'wins', 'losses', 'is_open', 'leader', 'my_clan_id',
            'is_highlighted', 'highlight_until', 'highlight_color', 'highlight_effect',
            'entry_fee', 'avatar_url',
        ] as $key) {
            $this->assertArrayHasKey($key, $data, "Пропало поле {$key}");
        }

        // Значения, а не только ключи
        $this->assertSame('Описание клана', $data['description']);
        $this->assertSame(30, $data['max_members']);
        $this->assertSame(1, $data['members_count']);

        // Лидер отдаётся карточкой игрока, где ник — username
        $this->assertSame($me->username, $data['leader']['username']);
    }

    public function test_list_still_exposes_expected_fields(): void
    {
        $me = User::factory()->create();
        $this->clan('Клан формы', $me);

        $response = $this->actingAs($me)->getJson('/api/clans')->assertOk();

        foreach (['id', 'name', 'tag', 'members_count', 'leader', 'my_clan_id'] as $key) {
            $this->assertArrayHasKey($key, $response->json('data.0'), "Пропало поле {$key}");
        }

        $this->assertSame(1, $response->json('data.0.members_count'));
    }

    public function test_search_still_works(): void
    {
        $me = User::factory()->create();
        $this->clan('Альфа', $me);
        $this->clan('Бета', User::factory()->create());

        $response = $this->actingAs($me)
            ->getJson('/api/clans?search=Альфа')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Альфа', $response->json('data.0.name'));
    }
}
