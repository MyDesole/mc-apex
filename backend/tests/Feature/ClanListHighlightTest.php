<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Данные подсветки в списке кланов.
 *
 * Фронтенд рисует эффект и цвет по этим полям, поэтому важно:
 *   - истёкшая подсветка не считается активной, даже если флаг остался;
 *   - цвет и эффект отдаются, чтобы карточка красилась купленным.
 */
class ClanListHighlightTest extends TestCase
{
    use RefreshDatabase;

    private function clan(array $attributes = []): Clan
    {
        $leader = User::factory()->create(['username' => 'ЛидерКлана']);

        $clan = Clan::create(array_merge([
            'name' => 'Клан списка',
            'tag' => 'LST',
            'leader_id' => $leader->id,
        ], $attributes));

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return $clan;
    }

    public function test_active_highlight_is_reported(): void
    {
        $this->clan([
            'is_highlighted' => true,
            'highlight_until' => now()->addDays(5),
            'highlight_color' => 'violet',
            'highlight_effect' => 'aurora',
        ]);

        $data = $this->getJson('/api/clans')->assertOk()->json('data.0');

        $this->assertTrue($data['is_highlighted']);
        $this->assertSame('violet', $data['highlight_color']);
        $this->assertSame('aurora', $data['highlight_effect']);
        $this->assertNotNull($data['highlight_until']);
    }

    public function test_expired_highlight_is_not_active(): void
    {
        $this->clan([
            'is_highlighted' => true,
            'highlight_until' => now()->subDay(),
            'highlight_color' => 'rose',
            'highlight_effect' => 'fire',
        ]);

        $data = $this->getJson('/api/clans')->assertOk()->json('data.0');

        $this->assertFalse(
            $data['is_highlighted'],
            'Истёкшая подсветка не должна считаться активной'
        );
    }

    public function test_clan_without_highlight_has_null_styling(): void
    {
        $this->clan();

        $data = $this->getJson('/api/clans')->assertOk()->json('data.0');

        $this->assertFalse($data['is_highlighted']);
        $this->assertNull($data['highlight_color']);
        $this->assertNull($data['highlight_effect']);
    }

    public function test_leader_username_is_present(): void
    {
        $this->clan();

        $data = $this->getJson('/api/clans')->assertOk()->json('data.0');

        $this->assertNotNull($data['leader'], 'Лидер должен быть в ответе');
        $this->assertSame('ЛидерКлана', $data['leader']['username']);

        // У игрока нет поля name — фронтенд должен брать username
        $this->assertArrayNotHasKey('name', $data['leader']);
    }

    public function test_my_clan_id_marks_own_clan(): void
    {
        $leader = User::factory()->create();
        $stranger = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Свой клан',
            'tag' => 'OWN',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        // Лидер видит свой клан помеченным
        $mine = $this->actingAs($leader)->getJson('/api/clans')->assertOk()->json('data.0');
        $this->assertSame($clan->id, $mine['my_clan_id']);

        // Посторонний — нет
        $other = $this->actingAs($stranger)->getJson('/api/clans')->assertOk()->json('data.0');
        $this->assertNull($other['my_clan_id']);
    }
}
