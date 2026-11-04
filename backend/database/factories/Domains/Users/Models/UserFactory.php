<?php

namespace Database\Factories\Domains\Users\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Модель задаётся явно.
     *
     * Laravel определяет её по имени класса фабрики, но для моделей в
     * доменах (App\Domains\...) эта эвристика не срабатывает: он
     * подставляет App\Models\... и получает App\User.
     */
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // В таблице users нет колонки name — только username (см. миграции)
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // ApexCoin и роль: чтобы тесты магазина не собирали это вручную
            'apex_coins' => 5000,
            // Вход в Цитадель в тестах открыт; закрытого игрока даёт ->citadelLocked()
            'citadel_unlocked_at' => now(),
            'role' => 'user',
            'is_banned' => false,

            /*
             * Очки рейтинга в проде считает RatingService по тирам, турнирам
             * и войнам. В тестах игрока «с местом в рейтинге» обычно задают
             * через tier_score — по умолчанию берём очки оттуда же.
             */
            'rating_points' => fn (array $attributes) => (int) ($attributes['tier_score'] ?? 0),
        ];
    }

    /** Игрок, который ещё не купил вход в Цитадель. */
    public function citadelLocked(): static
    {
        return $this->state(fn () => ['citadel_unlocked_at' => null]);
    }

    /**
     * Админ платформы.
     */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    /**
     * Модератор.
     */
    public function moderator(): static
    {
        return $this->state(fn () => ['role' => 'moderator']);
    }

    /**
     * Тестер тир-тестов.
     */
    public function tester(): static
    {
        return $this->state(fn () => ['role' => 'tester']);
    }

    /**
     * Игрок с заданным балансом ApexCoin.
     */
    public function withCoins(int $amount): static
    {
        return $this->state(fn () => ['apex_coins' => $amount]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
