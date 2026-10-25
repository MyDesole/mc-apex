<?php

namespace App\Support\Broadcasting;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Рассылка события в Reverb, которая не роняет запрос.
 *
 * Данные к этому моменту уже сохранены: если WebSocket-сервер лежит,
 * клиент всё равно получит ответ, а собеседник подтянет сообщение опросом.
 * Иначе пользователь видел 500 и при повторной отправке плодил дубли.
 */
class SafeBroadcast
{
    public static function send(object $event, bool $toOthers = false): void
    {
        try {
            $pending = broadcast($event);

            if ($toOthers) {
                $pending->toOthers();
            }

            // PendingBroadcast рассылает в деструкторе — вызываем его внутри try
            unset($pending);
        } catch (Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage(), ['event' => $event::class]);
        }
    }
}
