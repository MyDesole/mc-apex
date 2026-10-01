<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сетка доступа к админке.
 *
 * В routes/api.php админка разбита на два уровня:
 *   role:moderator,admin  — форум, комментарии, турниры, ачивки, магазин, кланы;
 *   role:admin            — юзеры, роли, аспекты, настройки сайта, новости, монеты.
 */
class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** Модераторские GET-эндпоинты (role:moderator,admin). */
    private const MODERATOR_GETS = [
        '/api/admin/forum/stats',
        '/api/admin/forum/categories',
        '/api/admin/forum/topics',
        '/api/admin/achievements',
        '/api/admin/comments',
        '/api/admin/clan-events',
        '/api/admin/tournaments',
        '/api/admin/clans',
        '/api/admin/shop-items',
    ];

    /** Только админские GET-эндпоинты (role:admin). */
    private const ADMIN_GETS = [
        '/api/admin/users',
        '/api/admin/users/verified',
        '/api/admin/site-settings',
        '/api/admin/news',
        '/api/admin/coin-transactions',
    ];

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_guest_gets_401_on_every_admin_endpoint(): void
    {
        foreach (array_merge(self::MODERATOR_GETS, self::ADMIN_GETS) as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_guest_gets_401_on_admin_post_endpoints(): void
    {
        $target = User::factory()->create();

        $this->postJson('/api/admin/tournaments', [])->assertUnauthorized();
        $this->postJson('/api/admin/forum/categories', [])->assertUnauthorized();
        $this->postJson('/api/admin/news', [])->assertUnauthorized();
        $this->putJson('/api/admin/site-settings', [])->assertUnauthorized();
        $this->postJson("/api/admin/users/{$target->id}/role", ['role' => 'admin'])->assertUnauthorized();
    }

    public function test_regular_user_is_forbidden_everywhere_in_admin_panel(): void
    {
        $user = $this->user('user');

        foreach (array_merge(self::MODERATOR_GETS, self::ADMIN_GETS) as $url) {
            $this->actingAs($user)->getJson($url)->assertForbidden();
        }
    }

    public function test_media_and_tester_roles_have_no_admin_access(): void
    {
        foreach (['media', 'tester'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->getJson('/api/admin/forum/stats')->assertForbidden();
            $this->actingAs($user)->getJson('/api/admin/tournaments')->assertForbidden();
            $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
        }
    }

    public function test_moderator_can_use_moderator_level_endpoints(): void
    {
        $moderator = $this->user('moderator');

        foreach (self::MODERATOR_GETS as $url) {
            $this->actingAs($moderator)->getJson($url)->assertOk();
        }
    }

    public function test_moderator_is_forbidden_on_admin_only_endpoints(): void
    {
        $moderator = $this->user('moderator');
        $target = User::factory()->create();

        foreach (self::ADMIN_GETS as $url) {
            $this->actingAs($moderator)->getJson($url)->assertForbidden();
        }

        $this->actingAs($moderator)
            ->postJson("/api/admin/users/{$target->id}/role", ['role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->postJson("/api/admin/users/{$target->id}/ban", ['reason' => 'нет'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->putJson("/api/admin/users/{$target->id}/aspects", [])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->postJson('/api/admin/news', ['title' => 'x', 'type' => 'news'])
            ->assertForbidden();
    }

    public function test_admin_can_use_both_levels(): void
    {
        $admin = $this->user('admin');

        foreach (array_merge(self::MODERATOR_GETS, self::ADMIN_GETS) as $url) {
            $this->actingAs($admin)->getJson($url)->assertOk();
        }
    }

    public function test_moderator_can_write_tournaments_and_achievements(): void
    {
        // Документируем текущее поведение: модератору открыты запись турниров,
        // ачивок и разделов форума (везде, где нужен только role:moderator,admin).
        $moderator = $this->user('moderator');

        $this->actingAs($moderator)
            ->postJson('/api/admin/tournaments', [
                'name' => 'Турнир модератора',
                'type' => 'solo',
                'format' => 'single_elim',
                'max_participants' => 8,
            ])
            ->assertCreated();

        $this->actingAs($moderator)
            ->postJson('/api/admin/achievements', [
                'name' => 'Ачивка модератора',
                'description' => 'Описание',
                'icon' => 'star',
                'color' => '#fff',
                'rarity' => 'common',
                'points' => 1,
            ])
            ->assertCreated();
    }

    public function test_banned_user_with_real_api_token_gets_403_with_reason(): void
    {
        $banned = User::factory()->create([
            'role' => 'admin',
            'is_banned' => true,
            'ban_reason' => 'Спам',
        ]);

        $token = $banned->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->assertSame('Ваш аккаунт забанен.', $response->json('message'));
        $this->assertSame('Спам', $response->json('reason'));

        // Middleware отзывает использованный токен
        $this->assertSame(0, $banned->tokens()->count());
    }

    /**
     * ПРЕ-СУЩЕСТВУЮЩИЙ БАГ (не чиним, только фиксируем поведение).
     *
     * EnsureUserIsNotBanned вызывает $user->currentAccessToken()?->delete().
     * Для сессионной (SPA, cookie-based) аутентификации Sanctum подставляет
     * Laravel\Sanctum\TransientToken, у которого НЕТ метода delete() — вместо
     * аккуратного 403 «Ваш аккаунт забанен.» летит фатальный Error (500).
     * Токенная аутентификация (см. тест выше) работает корректно.
     */
    public function test_banned_session_user_currently_crashes_instead_of_403(): void
    {
        $banned = User::factory()->create([
            'role' => 'admin',
            'is_banned' => true,
            'ban_reason' => 'Спам',
        ]);

        $this->actingAs($banned);

        $response = $this->getJson('/api/admin/users');

        // Текущее (некорректное) поведение: 500 с текстом фатальной ошибки
        $response->assertStatus(500);
        $this->assertStringContainsString(
            'TransientToken::delete()',
            (string) $response->json('message')
        );
    }
}
