<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftLinkCode;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Привязка майнкрафт-игрока к аккаунту и вход по паролю сайта.
 *
 * Устройство:
 *   1. игрок заходит на сервер — плагин просит код привязки;
 *   2. игрок вводит код на сайте под своей сессией — uuid привязывается;
 *   3. при следующих заходах плагин спрашивает пароль сайта.
 *
 * Пароль приходит от плагина по HTTPS и сразу проверяется хешем. Он не
 * логируется и не сохраняется.
 */
class MinecraftLinkService
{
    use AbortsWithMessage;

    /** Сколько попыток входа даём до блокировки. */
    public const MAX_ATTEMPTS = 5;

    /** На сколько блокируем после исчерпания попыток. */
    public const DECAY_SECONDS = 600;

    /** Сколько кодов привязки можно запросить за период. */
    private const LINK_CODES_PER_HOUR = 10;

    /** Заглушка для сверки, когда игрок не найден: чтобы время ответа не выдавало существование аккаунта. */
    private static ?string $dummyHash = null;

    /* ----------------------------- UUID ----------------------------- */

    /**
     * Приводит UUID к единому виду.
     *
     * Java отдаёт его с дефисами в нижнем регистре, но на вход может
     * прийти и без дефисов.
     */
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

    /* ----------------------------- Привязка ----------------------------- */

    /**
     * Выдаёт код привязки. Если игрок уже привязан — сообщает об этом.
     *
     * @return array{already_linked:bool, code:?string, formatted_code:?string, expires_in:?int, account_username:?string}
     */
    public function startLink(string $uuid, string $username): array
    {
        $user = $this->userByUuid($uuid);

        if ($user) {
            return [
                'already_linked' => true,
                'code' => null,
                'formatted_code' => null,
                'expires_in' => null,
                'account_username' => $user->username,
            ];
        }

        $this->throttleLinkCodes($uuid);

        $code = MinecraftLinkCode::generate();

        // Один активный код на игрока: новый затирает прежний
        MinecraftLinkCode::updateOrCreate(
            ['uuid' => $uuid],
            [
                'username' => $username,
                'code' => $code,
                'expires_at' => now()->addMinutes(MinecraftLinkCode::TTL_MINUTES),
            ],
        );

        return [
            'already_linked' => false,
            'code' => $code,
            'formatted_code' => MinecraftLinkCode::format($code),
            'expires_in' => MinecraftLinkCode::TTL_MINUTES * 60,
            'account_username' => null,
        ];
    }

    /** Привязан ли игрок и к какому аккаунту. */
    public function status(string $uuid): array
    {
        $user = $this->userByUuid($uuid);

        if ($user) {
            return [
                'linked' => true,
                'account_username' => $user->username,
            ];
        }

        $pending = MinecraftLinkCode::query()
            ->where('uuid', $uuid)
            ->where('expires_at', '>', now())
            ->exists();

        return [
            'linked' => false,
            'account_username' => null,
            // Код ещё жив — плагину не нужно выпрашивать новый
            'code_pending' => $pending,
        ];
    }

    /** Игрок ввёл код на сайте — привязываем. */
    public function link(User $user, string $rawCode): User
    {
        $code = MinecraftLinkCode::normalize($rawCode);

        if (strlen($code) !== MinecraftLinkCode::LENGTH) {
            $this->abortUnprocessable('Код должен состоять из 8 символов.');
        }

        $row = MinecraftLinkCode::query()
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            $this->abortUnprocessable('Код неверный или истёк. Зайди на сервер за новым.');
        }

        // UUID не должен быть занят другим аккаунтом
        $taken = User::query()
            ->where('minecraft_uuid', $row->uuid)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($taken) {
            $this->abortUnprocessable('Этот игрок уже привязан к другому аккаунту.');
        }

        // У аккаунта может быть прежняя привязка — заменяем
        $user->forceFill([
            'minecraft_uuid' => $row->uuid,
            'minecraft_username' => $row->username,
            'minecraft_linked_at' => now(),
        ])->save();

        $row->delete();

        return $user->fresh();
    }

    /** Отвязать игрока от аккаунта. */
    public function unlink(User $user): User
    {
        if ($user->minecraft_uuid) {
            MinecraftLinkCode::query()->where('uuid', $user->minecraft_uuid)->delete();
        }

        $user->forceFill([
            'minecraft_uuid' => null,
            'minecraft_username' => null,
            'minecraft_linked_at' => null,
        ])->save();

        return $user->fresh();
    }

    /* ----------------------------- Вход ----------------------------- */

    /**
     * Проверяет пароль сайта для привязанного игрока.
     *
     * @return array{ok:bool, reason:?string, attempts_left:int, retry_after:int, user:?User}
     */
    public function login(string $uuid, string $password): array
    {
        $key = 'minecraft-auth:' . $uuid;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return [
                'ok' => false,
                'reason' => 'throttled',
                'attempts_left' => 0,
                'retry_after' => RateLimiter::availableIn($key),
                'user' => null,
            ];
        }

        $user = $this->userByUuid($uuid);

        /*
         * Хеш сверяем всегда, даже когда игрок не найден. Иначе по времени
         * ответа можно было бы понять, привязан аккаунт или нет.
         */
        $hash = $user?->password ?? $this->dummyHash();

        if (! Hash::check($password, $hash) || ! $user) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            $used = RateLimiter::attempts($key);

            return [
                'ok' => false,
                'reason' => $user ? 'invalid' : 'not_linked',
                'attempts_left' => max(0, self::MAX_ATTEMPTS - $used),
                'retry_after' => 0,
                'user' => null,
            ];
        }

        RateLimiter::clear($key);

        return [
            'ok' => true,
            'reason' => null,
            'attempts_left' => self::MAX_ATTEMPTS,
            'retry_after' => 0,
            'user' => $user,
        ];
    }

    /* ----------------------------- Данные для игры ----------------------------- */

    /** Что показать игроку при входе: тир, звание бриджера, клан. */
    public function profilePayload(string $uuid): ?array
    {
        $user = $this->userByUuid($uuid);

        if (! $user) {
            return null;
        }

        $user->load(['bridgeRank', 'clanMember.clan:id,name,tag,banner_color']);

        return [
            'username' => $user->username,
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

    /* ----------------------------- Вспомогательное ----------------------------- */

    public function userByUuid(string $uuid): ?User
    {
        return User::query()->where('minecraft_uuid', $uuid)->first();
    }

    private function throttleLinkCodes(string $uuid): void
    {
        $key = 'minecraft-link-code:' . $uuid;

        if (RateLimiter::tooManyAttempts($key, self::LINK_CODES_PER_HOUR)) {
            $this->abortUnprocessable(
                'Слишком часто запрашиваются коды. Подожди '
                . RateLimiter::availableIn($key) . ' секунд.'
            );
        }

        RateLimiter::hit($key, 3600);
    }

    /** Валидный bcrypt-хеш для холостой сверки. */
    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('apex-timing-equalizer');
    }
}
