<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Очередь выдач лобби-покупок: сайт кладёт команды, плагин забирает и подтверждает.
 */
class MinecraftGrantService
{
    /** Не больше стольких попыток доставки, потом выдача помечается проваленной. */
    public const MAX_ATTEMPTS = 5;

    /** Проверка до списания монет: без привязанного ника выдавать некому. */
    public function assertCanReceive(User $user, ShopItem $item): void
    {
        if (! $user->minecraft_username) {
            throw new RuntimeException('Привяжите ник Minecraft в профиле: право выдаётся на сервере по нику.');
        }

        if (! $this->commandsFor($item, $user->minecraft_username)) {
            throw new RuntimeException('У этого предмета не заданы команды выдачи.');
        }
    }

    public function queue(User $user, ShopItem $item): MinecraftGrant
    {
        $nick = (string) $user->minecraft_username;

        return MinecraftGrant::create([
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'minecraft_username' => $nick,
            'commands' => $this->commandsFor($item, $nick),
            'status' => MinecraftGrant::PENDING,
        ]);
    }

    /**
     * Команды из metadata.commands с подстановкой {player}.
     * Ник проверяется строго, иначе в консоль можно протащить чужую команду.
     *
     * @return array<int, string>
     */
    public function commandsFor(ShopItem $item, string $nick): array
    {
        if (! preg_match('/^[A-Za-z0-9_]{3,16}$/', $nick)) {
            return [];
        }

        return collect($item->metadata['commands'] ?? [])
            ->filter(fn ($c) => is_string($c) && str_starts_with($c, 'lp '))
            ->map(fn (string $c) => str_replace('{player}', $nick, $c))
            ->values()
            ->all();
    }

    /** Ожидающие выдачи для плагина. */
    public function pending(int $limit = 20): Collection
    {
        return MinecraftGrant::query()
            ->where('status', MinecraftGrant::PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function acknowledge(MinecraftGrant $grant, bool $ok, ?string $error = null): MinecraftGrant
    {
        if ($grant->status !== MinecraftGrant::PENDING) {
            return $grant;
        }

        $grant->attempts++;

        if ($ok) {
            $grant->status = MinecraftGrant::DONE;
            $grant->delivered_at = now();
            $grant->error = null;
        } else {
            $grant->error = mb_substr((string) $error, 0, 250);

            if ($grant->attempts >= self::MAX_ATTEMPTS) {
                $grant->status = MinecraftGrant::FAILED;
            }
        }

        $grant->save();

        return $grant;
    }
}
