<?php

namespace Tests\Feature;

use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Полнота аспектов в профиле игрока.
 *
 * Регрессия: компонент профиля выводил аспекты по одному общему списку
 * полей, в котором не было бедварсных pvp, teamplay и building —
 * на профиле показывались только Game Sense и Bed Play, а сумма
 * считалась неверно. Здесь фиксируется, что сервер отдаёт все пять
 * полей каждого режима и что состав режимов различается.
 */
class ProfileAspectsCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_bedwars_profile_contains_all_five_aspects(): void
    {
        $user = User::factory()->create();

        PlayerAspectBedwars::create([
            'user_id' => $user->id,
            'pvp' => 20,
            'game_sense' => 18,
            'bed_play' => 16,
            'teamplay' => 14,
            'building' => 12,
        ]);

        $response = $this->getJson("/api/players/{$user->id}")->assertOk();

        $bedwars = $response->json('user.aspects.bedwars');

        $this->assertIsArray($bedwars);

        foreach (['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'] as $field) {
            $this->assertArrayHasKey($field, $bedwars, "Пропало поле аспекта {$field}");
            $this->assertNotNull($bedwars[$field], "Поле {$field} пустое");
        }

        // Сумма всех пяти — 80, и она должна быть доступна фронтенду
        $this->assertSame(80, array_sum(array_intersect_key($bedwars, array_flip([
            'pvp', 'game_sense', 'bed_play', 'teamplay', 'building',
        ]))));
    }

    public function test_pvp_profile_contains_its_own_five_aspects(): void
    {
        $user = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => 20,
            'rotka' => 18,
            'movement' => 16,
            'aim' => 14,
            'game_sense' => 12,
        ]);

        $response = $this->getJson("/api/players/{$user->id}")->assertOk();

        $pvp = $response->json('user.aspects.pvp');

        foreach (['block_placing', 'rotka', 'movement', 'aim', 'game_sense'] as $field) {
            $this->assertArrayHasKey($field, $pvp, "Пропало поле аспекта {$field}");
        }

        // У PvP нет бедварсных полей
        $this->assertArrayNotHasKey('bed_play', $pvp);
        $this->assertArrayNotHasKey('teamplay', $pvp);
    }

    public function test_mode_aspect_names_do_not_overlap_incorrectly(): void
    {
        $user = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => 5, 'rotka' => 5, 'movement' => 5, 'aim' => 5, 'game_sense' => 5,
        ]);

        PlayerAspectBedwars::create([
            'user_id' => $user->id,
            'pvp' => 7, 'game_sense' => 7, 'bed_play' => 7, 'teamplay' => 7, 'building' => 7,
        ]);

        $response = $this->getJson("/api/players/{$user->id}")->assertOk();

        $aspects = $response->json('user.aspects');

        // Оба режима присутствуют и не смешиваются
        $this->assertSame(5, $aspects['pvp']['block_placing']);
        $this->assertSame(7, $aspects['bedwars']['bed_play']);
        $this->assertArrayNotHasKey('pvp', $aspects['pvp'], 'У режима PvP нет поля pvp');
    }

    public function test_profile_without_aspects_is_still_valid(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/players/{$user->id}")->assertOk();

        // Ключи есть, значения пустые — фронтенд просто не покажет карточку режима
        $this->assertArrayHasKey('aspects', $response->json('user'));
        $this->assertNull($response->json('user.aspects.bedwars'));
    }
}
