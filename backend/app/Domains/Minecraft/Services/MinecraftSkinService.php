<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Скин игрока из Minecraft по привязанному нику.
 *
 * Браузер не ходит к Mojang напрямую: у textures.minecraft.net нет CORS,
 * а лимиты запросов у Mojang жёсткие. Поэтому сервер сам узнаёт UUID,
 * достаёт ссылку на текстуру из профиля, скачивает PNG и хранит у себя.
 *
 * Если Mojang недоступен, отдаём то, что уже лежит на диске: устаревший
 * скин лучше пустого места в профиле.
 *
 * Игрок может загрузить и свой PNG — тогда он важнее скина из Minecraft.
 */
class MinecraftSkinService
{
    private const DISK = 'local';

    private const DIR = 'minecraft-skins';

    /** Как часто перепроверять скин у Mojang. */
    private const SKIN_TTL = 6 * 3600;

    /** Как долго помнить UUID ника и «такого ника нет». */
    private const UUID_TTL = 24 * 3600;

    private const MISSING_TTL = 3600;

    private const TIMEOUT = 5;

    private const PROFILE_URL = 'https://api.mojang.com/users/profiles/minecraft/';

    private const SESSION_URL = 'https://sessionserver.mojang.com/session/minecraft/profile/';

    /** Свои скины игроков: {id}.png. */
    private const CUSTOM_DIR = 'custom-skins';

    /**
     * Скин игрока: путь к PNG на диске и модель рук (classic или slim).
     * Загруженный игроком скин важнее скина из Minecraft.
     *
     * @return array{path: string, model: string}|null
     */
    public function skinFor(User $user): ?array
    {
        if ($custom = $this->customFor($user)) {
            return $custom;
        }

        $uuid = $this->uuidFor($user);

        if (! $uuid) {
            return null;
        }

        $path = self::DIR . "/{$uuid}.png";
        $metaKey = "minecraft:skin:{$uuid}";

        $meta = Cache::get($metaKey);

        if ($meta && Storage::disk(self::DISK)->exists($path)) {
            return ['path' => Storage::disk(self::DISK)->path($path), 'model' => $meta['model']];
        }

        $fresh = $this->download($uuid, $path);

        if ($fresh) {
            Cache::put($metaKey, ['model' => $fresh['model']], self::SKIN_TTL);

            return ['path' => Storage::disk(self::DISK)->path($path), 'model' => $fresh['model']];
        }

        // Mojang не ответил — отдаём прошлый скин, если он есть
        if (Storage::disk(self::DISK)->exists($path)) {
            return ['path' => Storage::disk(self::DISK)->path($path), 'model' => $meta['model'] ?? 'classic'];
        }

        return null;
    }

    /**
     * Сохранить свой скин игрока. Принимаем только настоящую текстуру
     * скина: PNG 64×64 или старый 64×32.
     */
    public function saveCustom(User $user, string $bytes, string $model): void
    {
        if (! $this->isSkinPng($bytes)) {
            throw new RuntimeException('Нужен PNG-файл скина 64×64 (или старый 64×32), как для Minecraft.');
        }

        Storage::disk(self::DISK)->put($this->customPath($user), $bytes);

        $user->forceFill([
            'custom_skin_at' => now(),
            'custom_skin_model' => $model === 'slim' ? 'slim' : 'classic',
        ])->save();
    }

    /** Убрать свой скин — снова показываем скин из Minecraft. */
    public function removeCustom(User $user): void
    {
        Storage::disk(self::DISK)->delete($this->customPath($user));

        $user->forceFill(['custom_skin_at' => null, 'custom_skin_model' => null])->save();
    }

    /**
     * @return array{path: string, model: string}|null
     */
    private function customFor(User $user): ?array
    {
        $path = $this->customPath($user);

        if (! $user->custom_skin_at || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return ['path' => Storage::disk(self::DISK)->path($path), 'model' => $user->custom_skin_model ?: 'classic'];
    }

    private function customPath(User $user): string
    {
        return self::CUSTOM_DIR . "/{$user->id}.png";
    }

    /**
     * UUID без дефисов: так его ждут адреса Mojang.
     */
    private function uuidFor(User $user): ?string
    {
        if ($user->minecraft_uuid) {
            return str_replace('-', '', $user->minecraft_uuid);
        }

        $nickname = $user->minecraft_username;

        if (! $nickname || ! preg_match('/^[A-Za-z0-9_]{3,16}$/', $nickname)) {
            return null;
        }

        $key = 'minecraft:uuid:' . mb_strtolower($nickname);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::PROFILE_URL . $nickname);
        } catch (Throwable $e) {
            Log::info('Скины: Mojang недоступен', ['nickname' => $nickname, 'error' => $e->getMessage()]);

            return null;
        }

        $uuid = $response->successful() ? $response->json('id') : null;

        if (! is_string($uuid) || ! preg_match('/^[0-9a-f]{32}$/i', $uuid)) {
            // Пустая строка в кэше — «ника нет», чтобы не долбить Mojang
            if ($response->status() === 404 || $response->status() === 204) {
                Cache::put($key, '', self::MISSING_TTL);
            }

            return null;
        }

        $uuid = strtolower($uuid);

        Cache::put($key, $uuid, self::UUID_TTL);

        return $uuid;
    }

    /**
     * @return array{model: string}|null
     */
    private function download(string $uuid, string $path): ?array
    {
        try {
            $profile = Http::timeout(self::TIMEOUT)->get(self::SESSION_URL . $uuid);

            if (! $profile->successful()) {
                return null;
            }

            $textures = collect($profile->json('properties', []))
                ->firstWhere('name', 'textures');

            $decoded = $textures ? json_decode(base64_decode($textures['value'] ?? ''), true) : null;
            $skin = $decoded['textures']['SKIN'] ?? null;

            if (! $skin || empty($skin['url'])) {
                return null;
            }

            // Ссылка приходит от Mojang, но доверяем только их домену текстур
            $url = str_replace('http://', 'https://', $skin['url']);

            if (parse_url($url, PHP_URL_HOST) !== 'textures.minecraft.net') {
                return null;
            }

            $png = Http::timeout(self::TIMEOUT)->get($url);

            if (! $png->successful() || ! $this->isSkinPng($png->body())) {
                return null;
            }

            Storage::disk(self::DISK)->put($path, $png->body());

            return ['model' => ($skin['metadata']['model'] ?? null) === 'slim' ? 'slim' : 'classic'];
        } catch (Throwable $e) {
            Log::info('Скины: не удалось скачать скин', ['uuid' => $uuid, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** PNG размером 64×64 или старый 64×32. */
    private function isSkinPng(string $bytes): bool
    {
        $info = @getimagesizefromstring($bytes);

        return $info !== false
            && ($info['mime'] ?? null) === 'image/png'
            && $info[0] === 64
            && in_array($info[1], [32, 64], true);
    }
}
