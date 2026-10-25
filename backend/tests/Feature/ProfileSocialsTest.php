<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Соцсети профиля: все, что умеет показывать профиль, можно и сохранить.
 */
class ProfileSocialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_profile_socials_are_saved(): void
    {
        $user = User::factory()->create();

        $socials = [
            'discord' => 'desole',
            'telegram' => '@desole',
            'youtube' => 'https://youtube.com/@desole',
            'twitch' => 'https://twitch.tv/desole',
            'vk' => 'https://vk.com/desole',
            'steam' => 'https://steamcommunity.com/id/desole',
            'website' => 'mc-apex.ru',
        ];

        $this->actingAs($user)
            ->post('/api/players/me', ['_method' => 'PUT', 'socials' => $socials], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertEqualsCanonicalizing($socials, array_filter($user->fresh()->socials));
    }
}
