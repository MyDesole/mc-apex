<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Ошибки API приходят по-русски и без внутренностей фреймворка.
 *
 * Игрок не должен видеть «Unauthenticated.», «Server Error» или имя класса
 * модели: эти строки фронтенд показывает как есть.
 */
class ApiErrorMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_russian_message(): void
    {
        $this->getJson('/api/my-clan')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Нужно войти в аккаунт.');
    }

    public function test_missing_model_does_not_leak_class_name(): void
    {
        $response = $this->getJson('/api/clans/999999')->assertStatus(404);

        $this->assertStringNotContainsString('Clan', $response->json('message'));
        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $response->json('message'));
    }

    public function test_unknown_route_and_wrong_method_are_russian(): void
    {
        $this->getJson('/api/no-such-route')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Не найдено. Возможно, запись удалена или ссылка устарела.');

        $this->deleteJson('/api/clans')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Такое действие здесь не поддерживается.');
    }

    public function test_own_russian_message_is_kept(): void
    {
        $user = User::factory()->create();

        // Текст задан в коде (abort(403, '…')) — его менять нельзя
        $message = $this->actingAs($user)->getJson('/api/my-clan')->assertStatus(403)->json('message');

        $this->assertSame('Вы не в клане.', $message);
    }

    public function test_server_error_hides_details_outside_debug(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/_boom', fn () => throw new \RuntimeException('SQLSTATE[42000]: secret'));

        $response = $this->getJson('/api/_boom')->assertStatus(500);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertSame('Что-то сломалось на нашей стороне. Попробуйте ещё раз через минуту.', $response->json('message'));
    }

    public function test_throttle_message_tells_how_long_to_wait(): void
    {
        Route::get('/api/_limited', fn () => ['ok' => true])->middleware('throttle:1,1');

        $this->getJson('/api/_limited')->assertOk();

        $message = $this->getJson('/api/_limited')->assertStatus(429)->json('message');

        $this->assertStringStartsWith('Слишком много запросов. Подождите', $message);
    }

    public function test_validation_messages_are_russian(): void
    {
        $response = $this->postJson('/api/auth/login', [])->assertStatus(422);

        $this->assertSame('Заполните поле «пароль».', $response->json('errors.password.0'));
    }
}
