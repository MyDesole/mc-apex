<?php

namespace App\Domains\Minecraft\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Статус Minecraft-сервера по протоколу Server List Ping — тот же запрос,
 * что делает клиент игры для списка серверов. Плагины для этого не нужны.
 *
 * Ответ кэшируется: страница статуса и подвал не должны стучаться
 * в сервер на каждый просмотр.
 */
class MinecraftStatusService
{
    private const CACHE_KEY = 'minecraft:server-status';

    private const ONLINE_TTL = 30;

    private const OFFLINE_TTL = 15;

    private const TIMEOUT = 2.0;

    /**
     * @return array{configured: bool, online: bool, address: ?string, players: ?int, max: ?int, version: ?string, motd: ?string}
     */
    public function status(): array
    {
        $host = config('services.minecraft.host');
        $port = (int) config('services.minecraft.port', 25565);
        $address = config('services.minecraft.address') ?: $host;

        if (! $host) {
            return $this->result(false, false, null);
        }

        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $info = $this->ping($host, $port);
        $status = $this->result(true, $info !== null, $address, $info);

        Cache::put(self::CACHE_KEY, $status, $info ? self::ONLINE_TTL : self::OFFLINE_TTL);

        return $status;
    }

    /**
     * Сырой ответ сервера или null, если он не ответил.
     */
    protected function ping(string $host, int $port): ?array
    {
        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT);

            if (! $socket) {
                return null;
            }

            stream_set_timeout($socket, (int) ceil(self::TIMEOUT));

            // Handshake: версия протокола -1 (любая), адрес, порт, next state = 1 (status)
            $handshake = "\x00" . $this->varInt(-1) . $this->string($host) . pack('n', $port) . $this->varInt(1);
            fwrite($socket, $this->varInt(strlen($handshake)) . $handshake);

            // Status request
            fwrite($socket, "\x01\x00");

            $this->readVarInt($socket); // длина пакета
            $this->readVarInt($socket); // id пакета
            $length = $this->readVarInt($socket);

            $json = '';

            while (strlen($json) < $length && ! feof($socket)) {
                $chunk = fread($socket, $length - strlen($json));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $json .= $chunk;
            }

            fclose($socket);

            $data = json_decode($json, true);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function result(bool $configured, bool $online, ?string $address, ?array $info = null): array
    {
        return [
            'configured' => $configured,
            'online' => $online,
            'address' => $address,
            'players' => isset($info['players']['online']) ? (int) $info['players']['online'] : null,
            'max' => isset($info['players']['max']) ? (int) $info['players']['max'] : null,
            'version' => $info['version']['name'] ?? null,
            'motd' => $info ? $this->plainMotd($info['description'] ?? '') : null,
        ];
    }

    /** MOTD бывает строкой или деревом компонентов — собираем текст и убираем коды цвета §. */
    private function plainMotd(mixed $description): string
    {
        $text = is_string($description) ? $description : $this->flatten($description);

        return trim(preg_replace('/§./u', '', $text));
    }

    private function flatten(mixed $node): string
    {
        if (is_string($node)) {
            return $node;
        }

        if (! is_array($node)) {
            return '';
        }

        $text = $node['text'] ?? '';

        foreach ($node['extra'] ?? [] as $child) {
            $text .= $this->flatten($child);
        }

        return $text;
    }

    private function varInt(int $value): string
    {
        $value &= 0xFFFFFFFF;
        $out = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value ? $byte | 0x80 : $byte);
        } while ($value);

        return $out;
    }

    private function string(string $value): string
    {
        return $this->varInt(strlen($value)) . $value;
    }

    /** @param resource $socket */
    private function readVarInt($socket): int
    {
        $value = 0;
        $shift = 0;

        do {
            $byte = fread($socket, 1);

            if ($byte === false || $byte === '') {
                throw new \RuntimeException('Сервер оборвал соединение');
            }

            $byte = ord($byte);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80 && $shift < 35);

        return $value;
    }
}
