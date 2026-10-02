<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Снятие косметики профиля.
 *
 * Баг: фронтенд выкидывал null-поля из FormData, поэтому выбор
 * «Без эффекта» (profile_effect = null) не доходил до сервера и эффект
 * оставался надетым. Здесь фиксируется, что явная пустая строка
 * действительно очищает поле.
 */
class ProfileEffectClearingTest extends TestCase
{
    use RefreshDatabase;

    private function playerWithEffect(): User
    {
        return User::factory()->create([
            'profile_effect' => 'glow',
            'avatar_frame' => 'gold',
            'accent_color' => '#7c3aed',
            'status' => 'В игре',
        ]);
    }

    public function test_profile_effect_can_be_cleared_with_empty_string(): void
    {
        $user = $this->playerWithEffect();

        $this->actingAs($user)
            ->putJson('/api/players/me/profile', ['profile_effect' => ''])
            ->assertOk();

        $this->assertNull($user->fresh()->profile_effect);
    }

    public function test_profile_effect_can_be_changed(): void
    {
        $user = $this->playerWithEffect();

        $this->actingAs($user)
            ->putJson('/api/players/me/profile', ['profile_effect' => 'fire'])
            ->assertOk();

        $this->assertSame('fire', $user->fresh()->profile_effect);
    }

    public function test_absent_profile_effect_leaves_value_untouched(): void
    {
        $user = $this->playerWithEffect();

        // Поле не пришло — трогать его нельзя
        $this->actingAs($user)
            ->putJson('/api/players/me/profile', ['status' => 'Отошёл'])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('glow', $fresh->profile_effect);
        $this->assertSame('Отошёл', $fresh->status);
    }

    public function test_other_cosmetics_can_be_cleared_too(): void
    {
        $user = $this->playerWithEffect();

        $this->actingAs($user)
            ->putJson('/api/players/me/profile', [
                'status' => '',
                'quote' => '',
                'bio' => '',
                'discord_tag' => '',
            ])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->status);
        $this->assertNull($fresh->quote);
        $this->assertNull($fresh->bio);
        $this->assertNull($fresh->discord_tag);
    }

    public function test_unknown_effect_value_is_rejected(): void
    {
        $user = $this->playerWithEffect();

        // Слишком длинное значение не пройдёт max:32
        $this->actingAs($user)
            ->putJson('/api/players/me/profile', [
                'profile_effect' => str_repeat('x', 40),
            ])
            ->assertStatus(422);
    }

    public function test_favorite_modes_can_be_emptied(): void
    {
        $user = User::factory()->create([
            'favorite_modes' => ['bedwars', 'pvp'],
        ]);

        // Пустой массив означает «убрать все режимы»
        $this->actingAs($user)
            ->putJson('/api/players/me/profile', ['favorite_modes' => []])
            ->assertOk();

        $this->assertSame([], $user->fresh()->favorite_modes ?? []);
    }
}
