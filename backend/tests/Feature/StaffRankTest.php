<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PlayerProfileService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Место в топе: персонал не участвует.
 *
 * В списке рейтинга персонал исключался, а место в профиле считалось по
 * всем: администратору показывался значок места, которого в топе нет.
 */
class StaffRankTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlayerProfileService
    {
        return app(PlayerProfileService::class);
    }

    public function test_player_with_score_has_a_place(): void
    {
        $player = User::factory()->create(['role' => 'user', 'tier_score' => 50]);
        User::factory()->create(['role' => 'user', 'tier_score' => 100]);

        [$position, $total] = $this->service()->rankOf($player);

        $this->assertSame(2, $position);
        $this->assertSame(2, $total);
    }

    public function test_staff_has_no_place(): void
    {
        foreach (['admin', 'moderator', 'tester'] as $role) {
            $staff = User::factory()->create(['role' => $role, 'tier_score' => 500]);

            [$position, $total] = $this->service()->rankOf($staff);

            $this->assertNull($position, "Роль {$role} не должна иметь места");
            $this->assertSame(0, $total, "Роль {$role} не должна иметь общего числа");
        }
    }

    public function test_staff_does_not_shift_other_places(): void
    {
        // Персонал с большим счётом не должен сдвигать место игрока
        User::factory()->create(['role' => 'admin', 'tier_score' => 900]);
        User::factory()->create(['role' => 'moderator', 'tier_score' => 800]);

        $player = User::factory()->create(['role' => 'user', 'tier_score' => 100]);

        [$position, $total] = $this->service()->rankOf($player);

        $this->assertSame(1, $position, 'Персонал не учитывается в подсчёте места');
        $this->assertSame(1, $total);
    }

    public function test_media_participates_like_a_player(): void
    {
        $media = User::factory()->create(['role' => 'media', 'tier_score' => 70]);
        User::factory()->create(['role' => 'user', 'tier_score' => 100]);

        [$position, $total] = $this->service()->rankOf($media);

        $this->assertSame(2, $position, 'Медийка участвует в рейтинге');
        $this->assertSame(2, $total);
    }

    public function test_zero_score_has_no_place(): void
    {
        $player = User::factory()->create(['role' => 'user', 'tier_score' => 0]);

        [$position, $total] = $this->service()->rankOf($player);

        $this->assertNull($position);
        $this->assertSame(0, $total);
    }

    public function test_profile_marks_staff(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'tier_score' => 500]);
        $me = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($me);

        $response = $this->getJson("/api/players/{$staff->id}")->assertOk();

        $response->assertJsonPath('user.is_staff', true);
        $response->assertJsonPath('rank.position', null);
    }

    public function test_profile_does_not_mark_a_player_as_staff(): void
    {
        $player = User::factory()->create(['role' => 'user', 'tier_score' => 100]);
        $me = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($me);

        $this->getJson("/api/players/{$player->id}")
            ->assertOk()
            ->assertJsonPath('user.is_staff', false)
            ->assertJsonPath('rank.position', 1);
    }
}
