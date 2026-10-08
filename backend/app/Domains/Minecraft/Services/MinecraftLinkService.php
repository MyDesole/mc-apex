<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Привязка майнкрафт-игрока к аккаунту по нику.
 *
 * Как устроено:
 *   1. игрок заявляет свой ник в профиле на сайте — под своей сессией;
 *   2. ник уникален: два аккаунта не могут заявить один и тот же;
 *   3. плагин при заходе спрашивает, заявлен ли ник, и если да — чей;
 *   4. дальше игрок вводит пароль сайта, и вход разрешается.
 *
 * Ник, заявленный на сайте, и ник в игре должны совпадать. Поэтому зайти
 * под чужим ником нельзя: плагин потребует пароль владельца заявки, а не
 * того, кто зашёл.
 */
class MinecraftLinkService
{
    use AbortsWithMessage;

    /** Сколько попыток входа даём до блокировки. */
    public const MAX_ATTEMPTS = 5;

    /** На сколько блокируем после исчерпания попыток. */
    public const DECAY_SECONDS = 600;

    /** Ник майнкрафта: 3–16 символов, латиница, цифры и подчёркивание. */
    private const NICKNAME_PATTERN = '/^[A-Za-z0-9_]{3,16}$/';

    /** Заглушка для сверки, когда ник свободен: время ответа не должно выдавать разницу. */
    private static ?string $dummyHash = null;

    /* ----------------------------- Ник ----------------------------- */

    /** Приводит ник к единому виду: в майнкрафте регистр не важен. */
    public function normalizeNickname(string $nickname): string
    {
        return trim($nickname);
    }

    /** Годится ли строка на роль ника. */
    public function isValidNickname(string $nickname): bool
    {
        return (bool) preg_match(self::NICKNAME_PATTERN, $nickname);
    }

    /** Приводит UUID к единому виду. */
    public function normalizeUuid(string $uuid): string
    {
        $clean = strtolower(preg_replace('/[^a-f0-9]/i', '', $uuid) ?? '');

        if (strlen($clean) !== 32) {
            return strtolower(trim($uuid));
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($clean, 0, 8),
            substr($clean, 8, 4),
            substr($clean, 12, 4),
            substr($clean, 16, 4),
            substr($clean, 20, 12),
        );
    }

    /* ----------------------------- Заявка ника на сайте ----------------------------- */

    /** Игрок заявляет ник в профиле. */
    public function claimNickname(User $user, string $nickname): User
    {
        $nickname = $this->normalizeNickname($nickname);

        if (! $this->isValidNickname($nickname)) {
            $this->abortUnprocessable(
                'Ник может содержать только латинские буквы, цифры и подчёркивание, от 3 до 16 символов.'
            );
        }

        $taken = $this->userByNickname($nickname);

        if ($taken && $taken->getKey() !== $user->getKey()) {
            $this->abortUnprocessable('Этот ник уже заявлен другим аккаунтом.');
        }

        $user->forceFill([
            'minecraft_username' => $nickname,
            'minecraft_linked_at' => now(),
            // UUID узнаем только при первом входе: в offline-режиме его считает сервер
            'minecraft_uuid' => null,
        ])->save();

        return $user->fresh();
    }

    /** Снять заявку ника. */
    public function releaseNickname(User $user): User
    {
        $user->forceFill([
            'minecraft_uuid' => null,
            'minecraft_username' => null,
            'minecraft_linked_at' => null,
        ])->save();

        return $user->fresh();
    }

    /* ----------------------------- Запросы плагина ----------------------------- */

    /**
     * Заявлен ли ник и на какой аккаунт.
     *
     * Плагин по этому ответу решает: просить пароль или отправить игрока
     * заявить ник на сайте.
     */
    public function resolveNickname(string $nickname): array
    {
        $user = $this->userByNickname($nickname);

        if (! $user) {
            return [
                'claimed' => false,
                'nickname' => $this->normalizeNickname($nickname),
                'account_username' => null,
            ];
        }

        return [
            'claimed' => true,
            'nickname' => $user->minecraft_username,
            'account_username' => $user->username,
        ];
    }

    /**
     * Вход по нику и паролю сайта.
     *
     * @return array{ok:bool, reason:?string, attempts_left:int, retry_after:int, user:?User}
     */
    public function login(string $nickname, string $password, ?string $uuid = null): array
    {
        $nickname = $this->normalizeNickname($nickname);
        $key = 'minecraft-auth:' . mb_strtolower($nickname);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return [
                'ok' => false,
                'reason' => 'throttled',
                'attempts_left' => 0,
                'retry_after' => RateLimiter::availableIn($key),
                'user' => null,
            ];
        }

        $user = $this->userByNickname($nickname);

        /*
         * Хеш сверяем всегда, даже когда ник свободен: иначе по времени
         * ответа можно было бы узнать, заявлен ник или нет.
         */
        $hash = $user?->password ?? $this->dummyHash();

        if (! Hash::check($password, $hash) || ! $user) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return [
                'ok' => false,
                'reason' => $user ? 'invalid' : 'not_claimed',
                'attempts_left' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key)),
                'retry_after' => 0,
                'user' => null,
            ];
        }

        RateLimiter::clear($key);

        // Запоминаем UUID: в online-режиме он подтверждён сервером
        if ($uuid && $user->minecraft_uuid !== $uuid) {
            $user->forceFill(['minecraft_uuid' => $uuid])->save();
        }

        return [
            'ok' => true,
            'reason' => null,
            'attempts_left' => self::MAX_ATTEMPTS,
            'retry_after' => 0,
            'user' => $user,
        ];
    }

    /* ----------------------------- Инструменты админа ----------------------------- */

    /** На какой аккаунт заявлен ник. */
    public function lookupByNickname(string $nickname): ?array
    {
        $user = $this->userByNickname($nickname);

        if (! $user) {
            return null;
        }

        return [
            'claimed' => true,
            'nickname' => $user->minecraft_username,
            'account_username' => $user->username,
            'account_id' => $user->id,
            'uuid' => $user->minecraft_uuid,
            'claimed_at' => $user->minecraft_linked_at?->toIso8601String(),
        ];
    }

    /**
     * Освобождает ник от аккаунта.
     *
     * Нужно, когда ник заявил не тот человек: доказать принадлежность ника
     * в offline-режиме нельзя, поэтому решает админ.
     */
    public function adminRelease(string $nickname): bool
    {
        $user = $this->userByNickname($nickname);

        if (! $user) {
            return false;
        }

        $this->releaseNickname($user);

        return true;
    }

    /* ----------------------------- Данные для игры ----------------------------- */

    /** Что показать игроку при входе: тир, звание бриджера, клан. */
    public function profilePayload(User $user): array
    {
        $user->load(['bridgeRank', 'clanMember.clan:id,name,tag,banner_color']);

        return [
            'username' => $user->username,
            'nickname' => $user->minecraft_username,
            'tier' => $user->tier,
            'tier_score' => (int) $user->tier_score,
            'role' => $user->role,
            'clan_tag' => $user->clan_tag,
            'clan_color' => $user->clan_color,
            'avatar_url' => $user->avatar_url,
            'bridge_rank' => $user->bridgeRank
                ? [
                    'label' => $user->bridgeRank->label,
                    'color' => $user->bridgeRank->color,
                ]
                : null,
        ];
    }

    public function profileByNickname(string $nickname): ?array
    {
        $user = $this->userByNickname($nickname);

        return $user ? $this->profilePayload($user) : null;
    }

    /* ----------------------------- Вспомогательное ----------------------------- */

    /** Ищет аккаунт по нику. Регистр не важен: в майнкрафте он не различается. */
    public function userByNickname(string $nickname): ?User
    {
        return User::query()
            ->whereRaw('LOWER(minecraft_username) = ?', [mb_strtolower(trim($nickname))])
            ->first();
    }

    /** Валидный bcrypt-хеш для холостой сверки. */
    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('apex-timing-equalizer');
    }
}
